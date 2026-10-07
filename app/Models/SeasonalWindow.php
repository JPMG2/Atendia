<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonalWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'starts_at', 'ends_at', 'priority', 'is_active'])]
class SeasonalWindow extends Model implements DataTable
{
    /** @use HasFactory<SeasonalWindowFactory> */
    use HasFactory;

    // A window is never really deleted: its variants would dangle.
    use SoftDeletes;
    use TracksUserActions;

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
        static::saved(fn () => DemoTag::forgetResolved());
        static::deleted(fn () => DemoTag::forgetResolved());
    }

    /** @return HasMany<DemoTag, $this> */
    public function demoTags(): HasMany
    {
        return $this->hasMany(DemoTag::class);
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
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (self $window): array => [
                'id' => $window->id,
                'name' => $window->name,
                'starts_at' => $window->starts_at?->format('d/m/Y') ?? '',
                'ends_at' => $window->ends_at?->format('d/m/Y') ?? '',
                'priority' => $window->priority,
                'running' => $window->is_active && $window->coversToday(),
                'active' => $window->is_active,
            ])
            ->values();
    }

    /** Whether this window is the one talking today, not merely switched on. */
    public function coversToday(): bool
    {
        $today = self::today();

        return $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lessThanOrEqualTo($today)
            && $this->ends_at->greaterThanOrEqualTo($today);
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
