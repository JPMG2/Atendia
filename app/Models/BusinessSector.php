<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Closure;
use Database\Factories\BusinessSectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * The trade a business is in: health, food, beauty…
 *
 * A master the admin fills in. It groups {@see BusinessActivity}, which is the
 * level the assistant works with.
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'is_active'])]
class BusinessSector extends Model implements DataTable
{
    /** @use HasFactory<BusinessSectorFactory> */
    use HasFactory;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return HasMany<BusinessActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(BusinessActivity::class);
    }

    /**
     * The CONCRETE services suggested across this sector, wizard food: at
     * that point only the sector is known, so sibling trades pool their
     * lists. Deduped by name — the classics repeat in every trade — and
     * capped: chips invite, they do not enumerate. Once a business declares
     * its activities, the narrower per-activity lists take over.
     *
     * @return Collection<int, SuggestedService>
     */
    public function suggestedServices(int $limit = 12): Collection
    {
        return SuggestedService::query()
            ->where('is_active', true)
            ->whereHas(
                'activity',
                fn (Builder $query): Builder => $query->where('business_sector_id', $this->id),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique('name')
            ->take($limit)
            ->values();
    }

    /**
     * The service types SUGGESTED to this sector: the union of what its
     * activities suggest — the sector's twin of
     * {@see Business::suggestedServiceTypes()}. The type's own sector column
     * is admin grouping and takes no part here. A suggestion, never a fence.
     *
     * @return Collection<int, ServiceType>
     */
    public function suggestedServiceTypes(): Collection
    {
        return ServiceType::query()
            ->whereHas(
                'activities',
                fn (Builder $query): Builder => $query->where('business_activities.business_sector_id', $this->id),
            )
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A proper name: kept as typed, only the spacing is cleaned. Same criterion
     * as Province::normalizeName.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * The key is technical, not copy: always stored lowercase.
     */
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
     * Sorted by `sort_order` and not by name: the order is precisely what the
     * admin decides here, so the business sees them that way when choosing.
     *
     * @return Collection<int, array{id: int, code: string, name: string, description: string, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $sector): array => [
                    'id' => $sector->id,
                    'code' => $sector->code,
                    'name' => $sector->name,
                    'description' => $sector->description ?? '',
                    'order' => $sector->sort_order,
                    'active' => $sector->is_active,
                ],
            )
            ->values();
    }

    /**
     * Options for the sector combobox. An empty `states` does NOT filter, on
     * purpose: hiding a deactivated row would blank the field when editing a
     * record that uses it. `value` is opt-in like the catalog's `label`: the
     * wizard chips carry the CODE, existing callers keep the id.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the default
     * @param  (Closure(self): (int|string))|null  $value  the option value; null = the id
     * @return array<int, array{value: int|string, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null, ?Closure $value = null): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        $label ??= fn (self $sector): string => $sector->name;
        $value ??= fn (self $sector): int => $sector->id;

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $sector): array => [
                    'value' => $value($sector),
                    'label' => $label($sector),
                ],
            )
            ->all();
    }
}
