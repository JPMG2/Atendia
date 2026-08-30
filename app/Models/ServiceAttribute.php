<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A REUSABLE field a service type can carry.
 *
 * "Price" is the same attribute on a dish and on a combo. Which type carries
 * it — and whether it is required there — lives in the pivot, not here.
 */
#[Fillable(['code', 'name', 'description', 'data_type', 'unit', 'options', 'is_multiple', 'sort_order', 'is_active'])]
class ServiceAttribute extends Model implements DataTable
{
    /** @use HasFactory<ServiceAttributeFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;
    use TracksUserActions;

    /** What a `data_type` the system does not know falls back to. */
    public const string FALLBACK_DATA_TYPE = 'text';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'data_type', 'unit', 'options', 'is_multiple', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalog');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_multiple' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The service types already using this attribute.
     *
     * @return BelongsToMany<ServiceType, $this>
     */
    public function types(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'service_type_attribute')
            ->withPivot(['is_required', 'sort_order', 'label_override', 'hint_override'])
            ->withTimestamps();
    }

    /**
     * Whether any service type already uses it.
     *
     * It marks the line of what can still be touched: unused, the attribute is
     * free; used, changing its data type would break the values already stored.
     * It is also why it is deactivated rather than deleted.
     */
    public function isInUse(): bool
    {
        return $this->types()->exists();
    }

    /**
     * @return array<string, string>
     */
    public static function dataTypes(): array
    {
        return config('attribute_types');
    }

    /**
     * The readable label. A type no longer in config — removed after being used —
     * falls back to text instead of leaving the cell empty and suggesting the
     * attribute has no type at all.
     */
    public static function dataTypeLabel(string $dataType): string
    {
        $types = self::dataTypes();

        return $types[$dataType] ?? $types[self::FALLBACK_DATA_TYPE];
    }

    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public static function normalizeCode(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Options are typed into one comma-separated field. Spacing is cleaned and
     * empties are dropped: "Small, , Large" cannot slip a blank option into the
     * list the business ends up seeing.
     *
     * @param  string|array<int, string>|null  $value
     * @return array<int, string>|null
     */
    public static function normalizeOptions(string|array|null $value): ?array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        $items = array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), $items),
            static fn (string $item): bool => $item !== '',
        ));

        return $items === [] ? null : $items;
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
     * The data type described in a single cell: "Number - min - many".
     *
     * Type, unit and cardinality are three small facts that read at a glance
     * together; as three columns they took a third of the table and pushed it out
     * of the panel. Nothing is abbreviated — it is grouped.
     */
    public static function describeType(self $attribute): string
    {
        $parts = [self::dataTypeLabel($attribute->data_type)];

        if ($attribute->unit !== null && $attribute->unit !== '') {
            $parts[] = $attribute->unit;
        }

        if ($attribute->is_multiple) {
            $parts[] = __('catalog.service_attribute.multiple.on');
        }

        return implode(' · ', $parts);
    }

    /**
     * @return Collection<int, array{id: int, code: string, name: string, description: string, type: string, options: list<string>, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $attribute): array => [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'description' => $attribute->description ?? '',
                    // The row shows the LABEL, not the key: an admin reads a phrase,
                    // not an identifier.
                    'type' => self::describeType($attribute),
                    // A real list, not a comma string: the cell paints them as pills and
                    // the search box still finds them.
                    'options' => $attribute->options ?? [],
                    'order' => $attribute->sort_order,
                    'active' => $attribute->is_active,
                ],
            )
            ->values();
    }
}
