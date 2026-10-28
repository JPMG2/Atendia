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
use Closure;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['province_id', 'name', 'is_active'])]
class Region extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /** The company address is the only thing that stores a region. */
    public function usage(): array
    {
        return $this->onlyHeld(['companies' => $this->heldBy('companies', 'region_id')]);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'province_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A proper name: kept as typed, only the spacing is cleaned. Same criterion
     * as Country::normalizeName.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    /**
     * The `id` always travels: it is the only stable key for editing. The `name`
     * is user-editable, so it cannot identify the row.
     *
     * The country travels on the row too: otherwise you have to know by heart
     * which country each province is in. `country_id` is in the province select
     * on purpose — without it `country` comes back empty.
     *
     * @return Collection<int, array{id: int, name: string, province: string, country: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with(['province:id,name,country_id', 'province.country:id,name'])
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $region): array => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'province' => $region->province?->name ?? '',
                    'country' => $region->province?->country?->name ?? '',
                    'active' => $region->is_active,
                ],
            )
            ->values();
    }

    /** A region is looked up by its province or country as often as by its own name, so all three are searched. */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('name', __('catalog.region.columns.name'), CatalogCell::Name, fill: true, search: true),
            new CatalogColumnDto('province', __('catalog.region.columns.province'), CatalogCell::Text, search: true),
            new CatalogColumnDto('country', __('catalog.region.columns.country'), CatalogCell::Text, search: true, chips: true),
            new CatalogColumnDto('active', __('catalog.region.columns.status'), CatalogCell::Active, on: __('catalog.region.status.active'), off: __('catalog.region.status.inactive')),
        ];
    }

    /**
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it. The bare name is the default
     * because a province was picked first and already narrowed the list.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the default
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null, ?int $provinceId = null): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        $label ??= fn (self $region): string => $region->name;

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        if ($provinceId !== null) {
            $query->where('province_id', $provinceId);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $region): array => [
                    'value' => $region->id,
                    'label' => $label($region),
                ],
            )
            ->all();
    }
}
