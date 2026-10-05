<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    ];

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
     * The number a person writes to, digits only and ready for a wa.me link.
     *
     * One door: the landing, the pricing cards and a support ticket were each
     * reading `atendia.sales_whatsapp`, so changing who answers meant an env
     * var and a deploy. The env stays as the fallback while nobody saved the
     * screen, the same way the brand name works.
     */
    public static function whatsapp(): ?string
    {
        $number = self::current()?->phone ?: config('atendia.sales_whatsapp');
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region_id' => 'integer',
            'tax_condition_id' => 'integer',
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
