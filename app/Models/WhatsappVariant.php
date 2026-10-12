<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\OpeningHours;
use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\WhatsappVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * The number and the hours of the landing's WhatsApp inside one season.
 * Overrides {@see Company::reach()} field by field; a field left null inherits.
 */
#[Fillable(['seasonal_window_id', 'phone', 'opening_schedule', 'is_active'])]
class WhatsappVariant extends Model implements DataTable
{
    /** @use HasFactory<WhatsappVariantFactory> */
    use HasFactory;

    // A variant is never really deleted: the season it speaks in still exists.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seasonal_window_id' => 'integer',
            'opening_schedule' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The landing must show the edit now: she saves precisely to go and look.
        static::saved(fn () => Company::forgetReach());
        static::deleted(fn () => Company::forgetReach());
    }

    /** @return BelongsTo<SeasonalWindow, $this> */
    public function seasonalWindow(): BelongsTo
    {
        return $this->belongsTo(SeasonalWindow::class);
    }

    /**
     * Flips one variant's switch, null when it is gone: a season that
     * misbehaves in front of a visitor is one click, not an edit form.
     */
    public static function flipActive(int $id): ?self
    {
        $variant = self::query()->find($id);

        $variant?->update(['is_active' => ! $variant->is_active]);

        return $variant;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('seasonalWindow')
            ->get()
            ->sortByDesc(fn (self $variant) => $variant->seasonalWindow?->starts_at)
            ->map(fn (self $variant): array => [
                'id' => $variant->id,
                'season' => $variant->seasonalWindow?->name ?? '',
                'starts_at' => $variant->seasonalWindow?->starts_at?->format('d/m/Y') ?? '',
                'ends_at' => $variant->seasonalWindow?->ends_at?->format('d/m/Y') ?? '',
                'phone' => $variant->phone ?? '',
                // null inherits; a stored week, even an empty one, is its own.
                'opening_hours' => $variant->opening_schedule === null
                    ? ''
                    : (new OpeningHours(OpeningHours::normalize($variant->opening_schedule), Company::localTimezone()))->summary,
                'running' => $variant->is_active && ($variant->seasonalWindow?->is_active ?? false) && ($variant->seasonalWindow?->coversToday() ?? false),
                'active' => $variant->is_active,
            ])
            ->values();
    }
}
