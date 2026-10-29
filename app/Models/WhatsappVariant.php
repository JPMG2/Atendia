<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\OpeningHours;
use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
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
class WhatsappVariant extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<WhatsappVariantFactory> */
    use HasFactory;

    // A variant is never really deleted: the season it speaks in still exists.
    use SoftDeletes;
    use TracksUserActions;

    /** A variant only overrides the landing contact for its season. */
    public function usage(): array
    {
        return [];
    }

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
        // What a blank field falls back to on the landing (`Company::reachFor()`): the company's all-year answer.
        $company = Company::current();
        $inheritedPhone = $company?->phone ?: config('atendia.sales_whatsapp');
        $inheritedHours = $company?->opening_schedule === null
            ? null
            : (new OpeningHours(OpeningHours::normalize($company->opening_schedule), Company::localTimezone()))->summary;

        // The one the landing reads today, chosen as `Company::reachFor()` chooses it: overlapping seasons speak through one.
        $speakingId = self::speaking(SeasonalWindow::inForce())?->id;

        return $this->newQuery()
            ->with('seasonalWindow')
            ->get()
            ->sortByDesc(fn (self $variant) => $variant->seasonalWindow?->starts_at)
            ->map(fn (self $variant): array => [
                'id' => $variant->id,
                'season' => $variant->seasonalWindow?->name ?? '',
                'starts_at' => $variant->seasonalWindow?->starts_at?->format('d/m/Y') ?? '',
                'ends_at' => $variant->seasonalWindow?->ends_at?->format('d/m/Y') ?? '',
                // The season's dates, not the variant's: under the name, so a variant with its own number and week fits at 1280.
                'dates' => $variant->seasonalWindow === null ? '' : __('catalog.whatsapp_variant.dates', [
                    'from' => $variant->seasonalWindow->starts_at?->format('d/m/Y') ?? '',
                    'to' => $variant->seasonalWindow->ends_at?->format('d/m/Y') ?? '',
                ]),
                'phone' => $variant->phone ?? '',
                // null inherits; a stored week, even an empty one, is its own.
                'opening_hours' => $variant->opening_schedule === null
                    ? ''
                    : (new OpeningHours(OpeningHours::normalize($variant->opening_schedule), Company::localTimezone()))->summary,
                'running' => $variant->id === $speakingId,
                // A grey pill says why it is not speaking: its own switch, its season's switch, the dates, or a stronger season.
                'running_shown' => match (true) {
                    $variant->id === $speakingId => __('catalog.whatsapp_variant.status.running'),
                    ! $variant->is_active => __('catalog.whatsapp_variant.status.off'),
                    ! ($variant->seasonalWindow?->is_active ?? false) => __('catalog.whatsapp_variant.status.season_off'),
                    ! $variant->seasonalWindow->coversToday() => __('catalog.whatsapp_variant.status.waiting'),
                    default => __('catalog.whatsapp_variant.status.outranked'),
                },
                'active' => $variant->is_active,
            ])
            // Blank means "inherits", which is not the same as missing: said out loud, and greyed.
            ->map(fn (array $row): array => [
                ...$row,
                'phone_shown' => $row['phone'] === '' ? __('catalog.whatsapp_variant.status.inherits') : $row['phone'],
                'phone_inherits' => $row['phone'] === '',
                'hours_shown' => $row['opening_hours'] === '' ? __('catalog.whatsapp_variant.status.inherits') : $row['opening_hours'],
                'hours_inherits' => $row['opening_hours'] === '',
                'inherits_note' => self::inheritsNote($row['phone'] === '' ? $inheritedPhone : null, $row['opening_hours'] === '' ? $inheritedHours : null),
            ])
            // What it inherits is changed on the company screen: the door rides only on a row that names something inherited.
            ->map(fn (array $row): array => [...$row, 'company_url' => $row['inherits_note'] === '' ? null : route('admin.company')])
            ->values();
    }

    /**
     * What an inheriting row inherits, under the season's name: in its own cell it would push the table past
     * its box at 1280. Only what the company really has is named; nothing inherited, or nothing to inherit, is ''.
     */
    private static function inheritsNote(?string $phone, ?string $hours): string
    {
        $parts = array_filter([
            filled($phone) ? __('catalog.whatsapp_variant.inherits.phone', ['value' => $phone]) : null,
            filled($hours) ? __('catalog.whatsapp_variant.inherits.hours', ['value' => $hours]) : null,
        ]);

        return $parts === [] ? '' : __('catalog.whatsapp_variant.inherits.note', ['parts' => implode(' · ', $parts)]);
    }

    /**
     * The variant that speaks on the landing among the seasons in force: they come strongest first,
     * so the first one with a variant switched on wins. The landing and the list ask this same question.
     *
     * @param  Collection<int, SeasonalWindow>  $windows
     */
    public static function speaking(Collection $windows): ?self
    {
        if ($windows->isEmpty()) {
            return null;
        }

        $variants = self::query()
            ->where('is_active', true)
            ->whereIn('seasonal_window_id', $windows->pluck('id'))
            ->get();

        return $windows
            ->map(fn (SeasonalWindow $window): ?self => $variants->firstWhere('seasonal_window_id', $window->id))
            ->first(fn (?self $variant): bool => $variant !== null);
    }

    /** @return list<CatalogColumnDto> */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('season', __('catalog.whatsapp_variant.columns.season'), CatalogCell::Name, fill: true, search: true, searchAlso: ['phone', 'opening_hours'], sub: 'dates', note: 'inherits_note', link: ['key' => 'company_url', 'label' => __('catalog.whatsapp_variant.inherits.edit')]),
            new CatalogColumnDto('phone_shown', __('catalog.whatsapp_variant.columns.phone'), CatalogCell::Symbol, muted: 'phone_inherits'),
            new CatalogColumnDto('hours_shown', __('catalog.whatsapp_variant.columns.opening_hours'), CatalogCell::Symbol, muted: 'hours_inherits'),
            new CatalogColumnDto('running', __('catalog.whatsapp_variant.columns.running'), CatalogCell::Active, on: __('catalog.whatsapp_variant.status.running'), off: __('catalog.whatsapp_variant.status.waiting'), offFrom: 'running_shown'),
            new CatalogColumnDto('active', __('catalog.whatsapp_variant.columns.status'), CatalogCell::Active, on: __('catalog.whatsapp_variant.status.active'), off: __('catalog.whatsapp_variant.status.inactive')),
        ];
    }
}
