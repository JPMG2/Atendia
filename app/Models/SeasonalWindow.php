<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\HasHighlights;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
use App\Traits\TracksUserActions;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonalWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'starts_at', 'ends_at', 'priority', 'notice', 'is_active'])]
class SeasonalWindow extends Model implements DataTable, HasColumns, HasHighlights, Removable
{
    use CountsUsage;

    /** @use HasFactory<SeasonalWindowFactory> */
    use HasFactory;

    // A window is never really deleted: its variants would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /** Nothing outside the season depends on it: what hangs off it is its own. */
    public function usage(): array
    {
        return [];
    }

    /** The hero tags and WhatsApp variants of the season leave with it (the keys cascade). */
    public function owns(): array
    {
        return $this->onlyHeld([
            'demo_tags' => $this->heldBy('demo_tags', 'seasonal_window_id'),
            'whatsapp_variants' => $this->heldBy('whatsapp_variants', 'seasonal_window_id'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_date',
            'ends_at' => 'immutable_date',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Moving, switching off or deleting a season changes what the landing
        // shows right now. Here and not in the Actions: a row flipped from a
        // list is still a season changing, and two places would drift.
        static::saved(function (): void {
            DemoTag::forgetResolved();
            Company::forgetReach();
        });
        static::deleted(function (): void {
            DemoTag::forgetResolved();
            Company::forgetReach();
        });
    }

    /** @return HasMany<DemoTag, $this> */
    public function demoTags(): HasMany
    {
        return $this->hasMany(DemoTag::class);
    }

    /** @return HasMany<WhatsappVariant, $this> */
    public function whatsappVariants(): HasMany
    {
        return $this->hasMany(WhatsappVariant::class);
    }

    /**
     * The clock every season is read against. Fixed on purpose: the app runs on
     * UTC and visitors arrive from anywhere, so "today" has to mean one thing.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('atendia.seasonal_timezone'))->startOfDay();
    }

    /**
     * The windows in force on a given day, strongest first. Priority breaks the
     * tie, because two overlapping seasons with no order is a coin flip; the
     * newest row wins only when even that is equal.
     *
     * @return Collection<int, self>
     */
    public static function inForce(?CarbonImmutable $on = null): Collection
    {
        $day = ($on ?? self::today())->startOfDay();

        return self::query()
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $day)
            ->whereDate('ends_at', '>=', $day)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Flips one season's switch and hands it back, null when it is gone.
     * Lives here so the list can do it without a form: there is no field to
     * validate, and a season misbehaving in front of a visitor is one click.
     */
    public static function flipActive(int $id): ?self
    {
        $window = self::query()->find($id);

        $window?->update(['is_active' => ! $window->is_active]);

        return $window;
    }

    /** Names are proper nouns ("Navidad 2026"): kept as typed, spacing cleaned. */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            // A season is only dates: what it CHANGES hangs off it, and a season
            // with nothing hanging changes nothing. The list has to say so.
            ->withCount([
                'demoTags as hero_count' => fn ($query) => $query->where('is_active', true),
                'whatsappVariants as whatsapp_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (self $window): array => [
                'id' => $window->id,
                'name' => $window->name,
                'starts_at' => $window->starts_at?->format('d/m/Y') ?? '',
                'ends_at' => $window->ends_at?->format('d/m/Y') ?? '',
                'priority' => $window->priority,
                'running' => $window->is_active && $window->coversToday(),
                // A grey pill says why it is not showing: the switch, or the dates. Two causes, two fixes.
                'running_shown' => match (true) {
                    ! $window->is_active => __('catalog.seasonal_window.status.off'),
                    $window->coversToday() => __('catalog.seasonal_window.status.running'),
                    default => __('catalog.seasonal_window.status.waiting'),
                },
                'active' => $window->is_active,
                'hero' => (int) $window->hero_count,
                'whatsapp' => (int) $window->whatsapp_count,
                'has_notice' => filled($window->notice),
                'content_shown' => self::contentWords($window),
                'content_empty' => (int) $window->hero_count + (int) $window->whatsapp_count === 0 && blank($window->notice),
            ])
            ->values();
    }

    /**
     * What the season changes, said under its name: a season with no content is dates and nothing else,
     * and it should not look like it works.
     */
    private static function contentWords(self $window): string
    {
        $hero = (int) $window->hero_count;
        $whatsapp = (int) $window->whatsapp_count;

        $parts = array_filter([
            filled($window->notice) ? __('catalog.seasonal_window.content.notice') : null,
            $hero === 1 ? __('catalog.seasonal_window.content.hero_one') : ($hero > 1 ? __('catalog.seasonal_window.content.hero_many', ['count' => $hero]) : null),
            $whatsapp === 1 ? __('catalog.seasonal_window.content.whatsapp_one') : ($whatsapp > 1 ? __('catalog.seasonal_window.content.whatsapp_many', ['count' => $whatsapp]) : null),
        ]);

        return $parts === [] ? __('catalog.seasonal_window.content.none') : __('catalog.seasonal_window.content.some', ['parts' => implode(' · ', $parts)]);
    }

    /**
     * The season the landing speaks with today (the strongest in force, as `Company::reach()` picks it)
     * and how long it has left, counted in calendar days; with none, the landing is the all-year one.
     *
     * @return list<array{when: string, text: string, mono: bool}>
     */
    public function catalogHighlights(): array
    {
        $window = self::inForce()->first();
        $today = CarbonImmutable::parse(self::today()->format('Y-m-d'));

        if ($window === null) {
            // With none speaking, the next one that will: switched on and still to start (an off one never starts).
            $next = self::query()
                ->where('is_active', true)
                ->whereDate('starts_at', '>', $today)
                ->orderBy('starts_at')
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->first();
            $lines = [['when' => __('catalog.seasonal_window.highlights.none_when'), 'text' => __('catalog.seasonal_window.highlights.none'), 'mono' => false]];

            if ($next !== null) {
                $wait = (int) $today->diffInDays(CarbonImmutable::parse($next->starts_at->format('Y-m-d')), false);
                $lines[] = ['when' => $next->name, 'text' => trans_choice('catalog.seasonal_window.highlights.starts', $wait, ['count' => $wait, 'date' => $next->starts_at->format('d/m/Y')]), 'mono' => false];
            }

            return $lines;
        }

        $days = (int) $today->diffInDays(CarbonImmutable::parse($window->ends_at->format('Y-m-d')), false);

        return [[
            'when' => $window->name,
            'text' => trans_choice('catalog.seasonal_window.highlights.ends', $days, ['count' => $days, 'date' => $window->ends_at->format('d/m/Y')]),
            'mono' => false,
        ]];
    }

    /** @return list<CatalogColumnDto> */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('name', __('catalog.seasonal_window.columns.name'), CatalogCell::Name, fill: true, search: true, note: 'content_shown', noteWarning: 'content_empty'),
            new CatalogColumnDto('starts_at', __('catalog.seasonal_window.columns.starts_at'), CatalogCell::Symbol),
            new CatalogColumnDto('ends_at', __('catalog.seasonal_window.columns.ends_at'), CatalogCell::Symbol),
            new CatalogColumnDto('priority', __('catalog.seasonal_window.columns.priority'), CatalogCell::Symbol),
            new CatalogColumnDto('running', __('catalog.seasonal_window.columns.running'), CatalogCell::Active, on: __('catalog.seasonal_window.status.running'), off: __('catalog.seasonal_window.status.waiting'), offFrom: 'running_shown'),
            new CatalogColumnDto('active', __('catalog.seasonal_window.columns.status'), CatalogCell::Active, on: __('catalog.seasonal_window.status.active'), off: __('catalog.seasonal_window.status.inactive')),
            // Repeating creates a season: a control of the web; the phone reads only.
            new CatalogColumnDto('id', __('catalog.seasonal_window.columns.repeat'), CatalogCell::Text, webOnly: true),
        ];
    }

    /** Whether this window is the one talking today, not merely switched on. */
    public function coversToday(): bool
    {
        // Calendar days, as inForce() reads them: the dates are midnight UTC and today is midnight in the
        // seasons' zone, so comparing moments loses the season's last day.
        $today = self::today()->format('Y-m-d');

        return $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->format('Y-m-d') <= $today
            && $this->ends_at->format('Y-m-d') >= $today;
    }

    /**
     * Options for a combobox, newest season first.
     *
     * @param  list<bool>  $states  Empty does NOT filter: an editor still has to
     *                              resolve the window a saved row points at.
     * @return list<array{value: int, label: string}>
     */
    public static function options(array $states = []): array
    {
        return self::query()
            ->when($states !== [], fn ($query) => $query->whereIn('is_active', $states))
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (self $window): array => [
                'value' => $window->id,
                'label' => $window->name.' · '.($window->starts_at?->format('d/m/Y') ?? '').' – '.($window->ends_at?->format('d/m/Y') ?? ''),
            ])
            ->all();
    }
}
