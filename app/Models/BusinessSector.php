<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
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
     * Options for the sector combobox.
     *
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = []): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $sector): array => [
                    'value' => $sector->id,
                    'label' => $sector->name,
                ],
            )
            ->all();
    }
}
