<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * WHAT a business offers: an appointment, a dish, a table, a repair.
 *
 * Global, not tied to a sector. It inherits ONE modality. Which activities
 * suggest it is the pivot's business, and suggesting is never allowing
 * exclusively.
 */
#[Fillable(['code', 'name', 'description', 'service_modality_id', 'business_sector_id', 'sort_order', 'is_active'])]
class ServiceType extends Model implements DataTable
{
    /** @use HasFactory<ServiceTypeFactory> */
    use HasFactory;

    use LogsActivity;

    // A master row is never deleted: the pivot and the businesses that adopted it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'service_modality_id', 'business_sector_id', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalog');
    }

    /**
     * @return BelongsTo<ServiceModality, $this>
     */
    public function modality(): BelongsTo
    {
        // The FK is explicit: Eloquent would guess it from the method name, not
        // from the returned type.
        return $this->belongsTo(ServiceModality::class, 'service_modality_id');
    }

    /**
     * The sector is ONLY grouping for the admin screen. Who offers this type is
     * {@see self::activities()}'s call.
     *
     * @return BelongsTo<BusinessSector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(BusinessSector::class, 'business_sector_id');
    }

    /**
     * The attributes this type carries, with what belongs to THIS instance:
     * whether it is required here, its order and its label.
     *
     * It cannot be called `attributes()` — `$model->attributes` is Eloquent's own
     * column array, and the relation would be shadowed with nothing warning you.
     *
     * @return BelongsToMany<ServiceAttribute, $this>
     */
    public function serviceAttributes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceAttribute::class, 'service_type_attribute')
            ->withPivot(['is_required', 'sort_order', 'label_override', 'hint_override'])
            ->withTimestamps()
            ->orderBy('service_type_attribute.sort_order');
    }

    /**
     * The activities this type is SUGGESTED to. A missing row does not stop
     * anyone adopting it: the catalog offers, it does not forbid.
     *
     * @return BelongsToMany<BusinessActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessActivity::class, 'activity_service_type')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_modality_id' => 'integer',
            'business_sector_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** A proper name: kept as the admin typed it, only the spacing is cleaned. */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** The key is technical, not copy: always lowercase. */
    public static function normalizeCode(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeCode($value),
        );
    }

    /**
     * The row FLATTENS modality and sector to their readable names and hands the
     * attributes over as a list, which is what the table paints as chips.
     *
     * @return Collection<int, array{id: int, code: string, name: string, description: string, modality: string, sector: string, attributes: list<string>, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with(['modality:id,name', 'sector:id,name', 'serviceAttributes'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $type): array => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description ?? '',
                    // Null travels as '' — the catalog row's convention.
                    'modality' => $type->modality?->name ?? '',
                    'sector' => $type->sector?->name ?? '',
                    // With THIS type's label, not the global one: showing the global
                    // here would make the override look like it never applied.
                    'attributes' => $type->serviceAttributes
                        ->map(fn (ServiceAttribute $attribute): string => $attribute->pivot->label_override ?? $attribute->name)
                        ->values()
                        ->all(),
                    'order' => $type->sort_order,
                    'active' => $type->is_active,
                ],
            )
            ->values();
    }
}
