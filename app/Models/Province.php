<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Closure;
use Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['country_id', 'name', 'is_active'])]
class Province extends Model implements DataTable
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
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
     * @return Collection<int, array{id: int, name: string, country: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('country:id,name')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $province): array => [
                    'id' => $province->id,
                    'name' => $province->name,
                    'country' => $province->country?->name ?? '',
                    'active' => $province->is_active,
                ],
            )
            ->values();
    }

    /**
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it. `label` and `countryId` are
     * opt-in for the same reason — the default must fit the existing callers.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the default
     * @param  int|null  $countryId  country to narrow to; null = all
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null, ?int $countryId = null): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        $label ??= fn (self $province): string => $province->name.' — '.($province->country?->code ?? '—');

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        if ($countryId !== null) {
            $query->where('country_id', $countryId);
        }

        return $query
            ->with('country:id,code')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $province): array => [
                    'value' => $province->id,
                    'label' => $label($province),
                ],
            )
            ->all();
    }
}
