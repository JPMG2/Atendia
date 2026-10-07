<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
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
class DemoTag extends Model implements DataTable
{
    /** @use HasFactory<DemoTagFactory> */
    use HasFactory;

    // A tag is never really deleted: the demo business still points at its slug.
    use SoftDeletes;
    use TracksUserActions;

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
        return $this->newQuery()
            ->with('seasonalWindow')
            ->orderBy('slug')
            ->orderBy('seasonal_window_id')
            ->get()
            ->map(fn (self $tag): array => [
                'id' => $tag->id,
                'slug' => $tag->slug,
                'label' => $tag->label ?? '',
                'business_name' => $tag->business_name ?? '',
                'season' => $tag->seasonalWindow?->name ?? '',
                'sort_order' => $tag->sort_order,
                'active' => $tag->is_active,
            ])
            ->values();
    }
}
