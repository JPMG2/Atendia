<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\OpeningHours;
use App\Classes\Main\SendWindow;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Throwable;

/**
 * AtendIa itself, the one issuing the invoice. A SINGLE row, forever.
 *
 * Not to be confused with {@see Business}, the customer's business — the
 * tenant. What heads an invoice issued by AtendIa lives here.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legal_name',
        'brand_name',
        'tax_id',
        'region_id',
        'tax_condition_id',
        'address',
        'email',
        'phone',
        'web',
        'logo_path_light',
        'logo_path_dark',
        'text_copyright',
        'tagline',
        'payment_instructions',
        'opening_schedule',
        'whatsapp_greeting',
    ];

    /** Short, because a season turns at midnight and the landing is public. */
    private const int REACH_CACHE_MINUTES = 10;

    protected static function booted(): void
    {
        // The request that saves goes on to draw: what it remembered of the row is stale now.
        static::saved(function (): void {
            self::forgetReach();
            Once::flush();
        });
    }

    /**
     * The single company row, with what the public pages need.
     *
     * Memoised: the footer renders on every marketing page and it is the same
     * row every time. Null while nobody has saved the screen yet, which is why
     * every caller has to have a fallback.
     */
    public static function current(): ?self
    {
        return once(fn (): ?self => self::query()->with('socialLinks.socialNetwork')->first());
    }

    /**
     * The name the user reads, everywhere. The brand is being renamed (the
     * current one is taken), so it is a row and not a constant: the day it
     * changes, one field changes with it. Falls back to the configured name
     * while nobody has saved this screen yet.
     */
    public static function brand(): string
    {
        $brand = self::current()?->brand_name;

        return filled($brand) ? $brand : (string) config('app.name', 'AtendIa');
    }

    /**
     * The mark for each theme, one door for every header: the saved logo wins,
     * the bundled one stands in while nobody has uploaded. With only one theme
     * saved that file serves both, because a dark-only upload is rarer than a
     * single logo that already reads on both grounds.
     *
     * @return array{light: string, dark: string}
     */
    public static function logoUrls(): array
    {
        $company = self::current();

        $light = $company?->logo_path_light ? asset('storage/'.$company->logo_path_light) : self::versionedAsset('assets/logo-mark.svg');
        $dark = $company?->logo_path_dark
            ? asset('storage/'.$company->logo_path_dark)
            : ($company?->logo_path_light ? $light : self::versionedAsset('assets/logo-mark-dark.svg'));

        return ['light' => $light, 'dark' => $dark];
    }

    /**
     * A bundled file's URL stamped with its modified time. nginx serves
     * /assets/ as immutable for a year, so a replaced file keeps its URL and
     * browsers and Cloudflare would show the old one until it expires.
     * Touches no database: the error pages rely on that.
     */
    public static function versionedAsset(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }

    /**
     * The number a person writes to, digits only and ready for a wa.me link.
     *
     * One door: the landing, the pricing cards and a support ticket were each
     * reading `atendia.sales_whatsapp`, so changing who answers meant an env
     * var and a deploy. The env stays as the fallback while nobody saved the
     * screen, the same way the brand name works.
     */
    public static function whatsapp(): ?string
    {
        return self::reach()['phone'];
    }

    /**
     * How the landing's WhatsApp reads on a day: the company's own number,
     * hours and opening message, with what the strongest season in force
     * writes over them. `open` is null with no schedule declared (a missing
     * week is not a closed one) and for any day but today.
     *
     * @return array{phone: ?string, hours: ?string, open: ?bool, greeting: ?string, notice: ?string, season: ?string}
     */
    public static function reach(?CarbonImmutable $on = null): array
    {
        $today = SeasonalWindow::today();
        $day = ($on ?? $today)->startOfDay();
        $isToday = $day->equalTo($today);

        // Only today is cached: a preview is one person looking once. The
        // clock is NOT in the cache: "open now" changes by the minute.
        $resolved = $isToday
            ? Cache::remember('company-reach:'.$day->toDateString(), now()->addMinutes(self::REACH_CACHE_MINUTES), fn (): array => self::reachFor($day))
            : self::reachFor($day);

        $hours = $resolved['schedule'] === null
            ? null
            : new OpeningHours(OpeningHours::normalize($resolved['schedule']), self::localTimezone());

        $digits = preg_replace('/\D+/', '', (string) ($resolved['phone'] ?: config('atendia.sales_whatsapp'))) ?? '';

        return [
            'phone' => $digits === '' ? null : $digits,
            'hours' => $hours?->summary,
            'open' => $isToday ? $hours?->isOpenAt(CarbonImmutable::now()) : null,
            'greeting' => $resolved['greeting'],
            // `??`: an answer cached by the previous deploy has no such key.
            'notice' => $resolved['notice'] ?? null,
            'season' => $resolved['season'],
        ];
    }

    /**
     * The clock the schedule is read against: the country's own, the way a
     * business finds its. The first zone of a country is its main one.
     */
    public static function localTimezone(): string
    {
        $iso2 = self::current()?->region?->province?->country?->iso2;
        $zones = $iso2 === null ? [] : DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, strtoupper($iso2));

        return $zones[0] ?? (string) config('app.timezone');
    }

    /** The city the platform's clock is named after ("America/Caracas" reads "Caracas"): moves with the company's country. */
    public static function clockCity(): string
    {
        return str_replace('_', ' ', Str::afterLast(self::localTimezone(), '/'));
    }

    /**
     * Whether it is this exact minute (HH:MM, optionally on a weekday, 0 =
     * Sunday) on the platform's clock. Admin sends tick every minute through
     * this instead of `dailyAt()`, which reads the server's UTC.
     */
    public static function isNowAt(string $time, ?int $weekday = null): bool
    {
        $now = now(self::localTimezone());

        return $now->format('H:i') === $time && ($weekday === null || $now->dayOfWeek === $weekday);
    }

    /** Whether the admin's phone may be buzzed right now, on the platform's clock. */
    public static function inSendWindow(): bool
    {
        return SendWindow::containsHour(now(self::localTimezone()));
    }

    public static function forgetReach(): void
    {
        Cache::forget('company-reach:'.SeasonalWindow::today()->toDateString());
    }

    /**
     * @return array{phone: ?string, schedule: ?array<array-key, mixed>, greeting: ?string, notice: ?string, season: ?string}
     */
    private static function reachFor(CarbonImmutable $day): array
    {
        $company = self::current();
        $windows = SeasonalWindow::inForce($day);
        $variant = WhatsappVariant::speaking($windows);

        // The strongest season that wrote a notice; its name is the one the footer shows,
        // since the visible text belongs to it. With no notice, the one whose variant speaks.
        $noticed = $windows->first(fn (SeasonalWindow $window): bool => filled($window->notice));
        $speaking = $variant === null ? null : $windows->firstWhere('id', $variant->seasonal_window_id);

        return [
            // Raw: the env fallback is applied outside the cache, where a changed value shows at once.
            'phone' => $variant?->phone ?: $company?->phone,
            // [] on a variant is "closed all week", so only null inherits.
            'schedule' => $variant?->opening_schedule ?? $company?->opening_schedule,
            'greeting' => $company?->whatsapp_greeting,
            'notice' => $noticed?->notice,
            'season' => ($noticed ?? $speaking)?->name,
        ];
    }

    /**
     * What the footer, the mail chrome and the error pages print about the
     * company. Never throws: those surfaces must render while the database
     * is the thing that is down, so a failed read is an empty row.
     *
     * @return array{legal_name: string, email: ?string, address: ?string, place: string, links: Collection<int, SocialLink>}
     */
    public static function contact(): array
    {
        try {
            $company = self::current();
            $region = $company?->region;
            $contact = [
                'legal_name' => filled($company?->legal_name) ? $company->legal_name : self::brand(),
                'email' => filled($company?->email) ? $company->email : null,
                'address' => filled($company?->address) ? $company->address : null,
                'place' => collect([$region?->name, $region?->province?->name, $region?->province?->country?->name])
                    ->filter()
                    ->implode(', '),
                'links' => $company?->socialLinks ?? collect(),
            ];
        } catch (Throwable) {
            $contact = [
                'legal_name' => (string) config('app.name', 'AtendIa'),
                'email' => null,
                'address' => null,
                'place' => '',
                'links' => collect(),
            ];
        }

        return $contact;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region_id' => 'integer',
            'tax_condition_id' => 'integer',
            'opening_schedule' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * The issuer's tax standing in Argentina.
     *
     * @return BelongsTo<TaxCondition, $this>
     */
    public function taxCondition(): BelongsTo
    {
        return $this->belongsTo(TaxCondition::class);
    }

    /**
     * The networks the account is on, in display order.
     *
     * The relation is polymorphic: one table holds the company's networks and
     * every business's ({@see SocialLink}).
     *
     * @return MorphMany<SocialLink, $this>
     */
    public function socialLinks(): MorphMany
    {
        return $this->morphMany(SocialLink::class, 'linkable')->orderBy('sort_order');
    }

    /** How clients pay Atendia, as the admin wrote it; null until it is set. */
    public static function paymentInstructions(): ?string
    {
        return self::current()?->payment_instructions;
    }
}
