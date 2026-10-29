<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
use App\Traits\TracksUserActions;
use Carbon\CarbonImmutable;
use Database\Factories\DemoTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['slug', 'seasonal_window_id', 'label', 'business_name', 'noun', 'chips', 'pool', 'sort_order', 'is_active'])]
class DemoTag extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<DemoTagFactory> */
    use HasFactory;

    // A tag is never really deleted: the demo business still points at its slug.
    use SoftDeletes;
    use TracksUserActions;

    /** A hero tag is the leaf of the tree: it holds nothing and nobody holds it. */
    public function usage(): array
    {
        return [];
    }

    /** Short, because the landing is public and a season turns at midnight. */
    private const int CACHE_MINUTES = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chips' => 'array',
            'pool' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Saving a tag or a season has to reach the landing now, not in ten
        // minutes: she edits precisely to go and look at it.
        static::saved(fn () => self::forgetResolved());
        static::deleted(fn () => self::forgetResolved());
    }

    /** @return BelongsTo<SeasonalWindow, $this> */
    public function seasonalWindow(): BelongsTo
    {
        return $this->belongsTo(SeasonalWindow::class);
    }

    /** True for the row that answers when no season is running. */
    public function isEvergreen(): bool
    {
        return $this->seasonal_window_id === null;
    }

    /**
     * What the hero shows on a given day: every evergreen tag, with the fields
     * of the strongest season in force written over it. A variant that fills
     * nothing changes nothing, and a day with no season reads the evergreen.
     *
     * @return list<array{slug: string, label: string, name: string, noun: string, chips: list<string>, pool: list<array{side: string, text: string}>, season: ?string}>
     */
    public static function resolved(?CarbonImmutable $on = null): array
    {
        $today = SeasonalWindow::today();
        $day = ($on ?? $today)->startOfDay();

        // Only today is worth caching. A preview is one person looking once,
        // and caching other days would leave stale answers nobody can find.
        return $day->equalTo($today)
            ? Cache::remember('demo-tags:'.$day->toDateString(), now()->addMinutes(self::CACHE_MINUTES), fn (): array => self::resolveFor($day))
            : self::resolveFor($day);
    }

    public static function forgetResolved(): void
    {
        Cache::forget('demo-tags:'.SeasonalWindow::today()->toDateString());
    }

    /**
     * @return list<array{slug: string, label: string, name: string, noun: string, chips: list<string>, pool: list<array{side: string, text: string}>, season: ?string}>
     */
    private static function resolveFor(CarbonImmutable $day): array
    {
        $windows = SeasonalWindow::inForce($day);

        /** @var Collection<int, self> $variants */
        $variants = $windows->isEmpty()
            ? collect()
            : self::query()
                ->where('is_active', true)
                ->whereIn('seasonal_window_id', $windows->pluck('id'))
                ->get();

        return self::query()
            ->whereNull('seasonal_window_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (self $tag) use ($windows, $variants): array {
                // Windows come strongest first, so the first hit is the winner.
                $variant = $windows
                    ->map(fn (SeasonalWindow $window): ?self => $variants
                        ->first(fn (self $row): bool => $row->slug === $tag->slug && $row->seasonal_window_id === $window->id))
                    ->first(fn (?self $row): bool => $row !== null);

                return [
                    'slug' => $tag->slug,
                    'label' => $variant?->label ?? $tag->label ?? $tag->slug,
                    'name' => $variant?->business_name ?? $tag->business_name ?? '',
                    'noun' => $variant?->noun ?? $tag->noun ?? '',
                    'chips' => array_values($variant?->chips ?? $tag->chips ?? []),
                    'pool' => array_values($variant?->pool ?? $tag->pool ?? []),
                    'season' => $windows->firstWhere('id', $variant?->seasonal_window_id)?->name,
                ];
            })
            ->values()
            ->all();
    }

    /** What a scripted line is prefixed with, so a side is typed, not guessed. */
    public const string SIDE_IN = 'cliente';

    public const string SIDE_OUT = 'asistente';

    /**
     * @param  list<string>|null  $chips
     */
    public static function chipsToText(?array $chips): ?string
    {
        return $chips === null || $chips === [] ? null : implode("\n", $chips);
    }

    /**
     * @return list<string>|null
     */
    public static function textToChips(?string $text): ?array
    {
        $lines = self::lines($text);

        return $lines === [] ? null : $lines;
    }

    /**
     * The scripted conversation as she writes it: one bubble per line, each
     * saying who speaks. Order is the whole point, so a grid of two columns
     * would lose exactly what matters.
     *
     * @param  list<array{side: string, text: string}>|null  $pool
     */
    public static function scriptToText(?array $pool): ?string
    {
        if ($pool === null || $pool === []) {
            return null;
        }

        return implode("\n", array_map(
            fn (array $line): string => (($line['side'] ?? 'in') === 'in' ? self::SIDE_IN : self::SIDE_OUT).': '.($line['text'] ?? ''),
            $pool,
        ));
    }

    /**
     * A line with no prefix is kept as the customer's: dropping it would eat
     * her text silently, and the rule says so right under the field.
     *
     * @return list<array{side: string, text: string}>|null
     */
    public static function textToScript(?string $text): ?array
    {
        $pool = [];

        foreach (self::lines($text) as $line) {
            $side = str_starts_with(mb_strtolower($line), self::SIDE_OUT.':') ? 'out' : 'in';
            $body = trim((string) preg_replace('/^('.self::SIDE_IN.'|'.self::SIDE_OUT.')\s*:/iu', '', $line));

            if ($body !== '') {
                $pool[] = ['side' => $side, 'text' => $body];
            }
        }

        return $pool === [] ? null : $pool;
    }

    /**
     * @return list<string>
     */
    private static function lines(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /** The slug is a key, not a title: lowercase, no spaces, no accents. */
    public static function normalizeSlug(string $value): string
    {
        return str((string) preg_replace('/\s+/u', '-', trim($value)))->ascii()->lower()->toString();
    }

    /** Proper names, kept as typed with the spacing cleaned. */
    public static function normalizeLabel(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function catalogRows(): Collection
    {
        $today = SeasonalWindow::today();

        return $this->newQuery()
            ->with('seasonalWindow')
            ->orderBy('slug')
            ->orderBy('seasonal_window_id')
            ->get()
            ->map(function (self $tag) use ($today): array {
                // Blank on a variant means "inherits", which is not the same as missing: said out loud,
                // or the row reads as half loaded. Blank on the year-round row is simply blank.
                $inherits = blank($tag->business_name) && $tag->seasonalWindow !== null;

                return [
                    'id' => $tag->id,
                    'slug' => $tag->slug,
                    'label' => $tag->label ?? '',
                    'business_name' => $tag->business_name ?? '',
                    'business_shown' => $inherits ? __('catalog.demo_tag.status.inherits') : ($tag->business_name ?? ''),
                    'business_inherits' => $inherits,
                    'season' => $tag->seasonalWindow?->name ?? '',
                    'season_shown' => self::seasonWords($tag->seasonalWindow, $today),
                    'sort_order' => $tag->sort_order,
                    'active' => $tag->is_active,
                    // The hero's own `?rubro=` door, only on the rows that are its pills: the year-round ones that
                    // are on. A season's row has no pill of its own; it is seen ahead in the dated preview below.
                    'landing_url' => $tag->is_active && $tag->seasonal_window_id === null ? rtrim(url('/'), '/').'/?rubro='.rawurlencode($tag->slug) : null,
                ];
            })
            ->values();
    }

    /** The season as the list says it: "Siempre" with none, and how far off it is while it has not started. */
    private static function seasonWords(?SeasonalWindow $window, CarbonImmutable $today): string
    {
        if ($window === null) {
            return __('catalog.demo_tag.status.evergreen');
        }

        // Calendar days, not hours: "today" is in the seasons' zone and the date was born at midnight UTC.
        $days = $window->starts_at === null ? 0 : (int) CarbonImmutable::parse($today->format('Y-m-d'))
            ->diffInDays(CarbonImmutable::parse($window->starts_at->format('Y-m-d')), false);

        // A season that is switched off never starts: no countdown for it.
        return $days > 0 && $window->is_active ? trans_choice('catalog.demo_tag.status.starts_in', $days, ['name' => $window->name, 'count' => $days]) : $window->name;
    }

    /** @return list<CatalogColumnDto> */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('slug', __('catalog.demo_tag.columns.slug'), CatalogCell::Code, search: true),
            new CatalogColumnDto('label', __('catalog.demo_tag.columns.label'), CatalogCell::Name, fill: true, search: true, searchAlso: ['business_name', 'season'], link: ['key' => 'landing_url', 'label' => __('catalog.demo_tag.landing')]),
            new CatalogColumnDto('business_shown', __('catalog.demo_tag.columns.business_name'), CatalogCell::Symbol, muted: 'business_inherits'),
            new CatalogColumnDto('season_shown', __('catalog.demo_tag.columns.season'), CatalogCell::Symbol),
            new CatalogColumnDto('sort_order', __('catalog.demo_tag.columns.sort_order'), CatalogCell::Symbol),
            new CatalogColumnDto('active', __('catalog.demo_tag.columns.status'), CatalogCell::Active, on: __('catalog.demo_tag.status.active'), off: __('catalog.demo_tag.status.inactive')),
        ];
    }
}
