<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
use App\Traits\EditedByForm;
use App\Traits\TracksUserActions;
use Closure;
use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * What a business marks inside its activity: a medical specialty, a cuisine…
 *
 * It describes the business to its customers and to the assistant. It never
 * changes the suggested services: those depend on the activity alone.
 */
#[Fillable(['business_activity_id', 'code', 'name', 'sort_order', 'is_active'])]
class Specialty extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;
    use EditedByForm;

    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory;

    // A master row is never deleted: the businesses that marked it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /** Businesses that marked it: with any, the master is switched off, not removed. */
    public function usage(): array
    {
        return $this->onlyHeld(['businesses' => $this->heldBy('business_specialty', 'specialty_id')]);
    }

    /**
     * The activity that offers it (a required FK on the table).
     *
     * @return BelongsTo<BusinessActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(BusinessActivity::class, 'business_activity_id');
    }

    /**
     * The businesses that marked it.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_specialty')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Options for a specialty picker. Same conventions as its siblings: an empty
     * `states` or `activityIds` does not filter, `label`/`value` are opt-in (the
     * wizard carries the code).
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the name
     * @param  (Closure(self): (int|string))|null  $value  the option value; null = the id
     * @param  list<int>  $activityIds  activities to narrow to; empty = all
     * @return array<int, array{value: int|string, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null, ?Closure $value = null, array $activityIds = []): array
    {
        $query = self::query();

        $label ??= fn (self $specialty): string => $specialty->name;
        $value ??= fn (self $specialty): int => $specialty->id;

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        if ($activityIds !== []) {
            $query->whereIn('business_activity_id', $activityIds);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $specialty): array => [
                    'value' => $value($specialty),
                    'label' => $label($specialty),
                ],
            )
            ->all();
    }

    /**
     * The ids a list of codes stands for, limited to the activities offered.
     *
     * A code that is not active or not offered by those activities is dropped:
     * the form validates, this only guards what reaches the pivot.
     *
     * @param  list<string>  $codes
     * @param  list<int>  $activityIds
     * @return list<int>
     */
    public static function idsFromCodes(array $codes, array $activityIds): array
    {
        if ($codes === [] || $activityIds === []) {
            return [];
        }

        return self::query()
            ->whereIn('code', $codes)
            ->whereIn('business_activity_id', $activityIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_activity_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => BusinessSector::normalizeName($value),
        );
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => BusinessSector::normalizeCode($value),
        );
    }

    /**
     * Grouped by activity and, inside each, by their order: how the business
     * will see them when choosing.
     *
     * @return Collection<int, array{id: int, code: string, name: string, activity: string, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()->with('activity:id,name,sort_order')
            ->get()
            ->sortBy([fn (self $a, self $b): int => ($a->activity?->sort_order ?? 0) <=> ($b->activity?->sort_order ?? 0), fn (self $a, self $b): int => $a->sort_order <=> $b->sort_order, fn (self $a, self $b): int => strcmp($a->name, $b->name)])
            ->map(
                fn (self $specialty): array => [
                    'id' => $specialty->id,
                    'code' => $specialty->code,
                    'name' => $specialty->name,
                    'activity' => $specialty->activity?->name ?? '',
                    'order' => $specialty->sort_order,
                    'active' => $specialty->is_active,
                ],
            )
            ->values();
    }

    /** A specialty is looked up by its key, its name or the activity it belongs to. */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('code', __('catalog.specialty.columns.code'), CatalogCell::Code, search: true),
            new CatalogColumnDto('name', __('catalog.specialty.columns.name'), CatalogCell::Name, fill: true, search: true),
            new CatalogColumnDto('activity', __('catalog.specialty.columns.activity'), CatalogCell::Text, search: true),
            new CatalogColumnDto('order', __('catalog.specialty.columns.order'), CatalogCell::Symbol),
            new CatalogColumnDto('active', __('catalog.specialty.columns.status'), CatalogCell::Active, on: __('catalog.specialty.status.active'), off: __('catalog.specialty.status.inactive')),
        ];
    }
}
