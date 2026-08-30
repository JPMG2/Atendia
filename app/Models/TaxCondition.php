<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Closure;
use Database\Factories\TaxConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['country_id', 'name', 'code', 'discriminate_tax', 'is_active'])]
class TaxCondition extends Model implements DataTable
{
    /** @use HasFactory<TaxConditionFactory> */
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
            'discriminate_tax' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', '', $value)));
    }

    /**
     * A proper name: kept as typed, only the spacing is cleaned.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeCode($value),
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    /**
     * The `id` always travels: it is the only stable key for editing. The `code`
     * is user-editable, so it cannot identify the row.
     *
     * @return Collection<int, array{id: int, code: string, name: string, country: string, discriminates: bool, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('country:id,code')
            ->orderBy('code')
            ->get()
            ->map(
                fn (self $condition): array => [
                    'id' => $condition->id,
                    'code' => $condition->code,
                    'name' => $condition->name,
                    'country' => $condition->country?->code ?? '',
                    'discriminates' => $condition->discriminate_tax,
                    'active' => $condition->is_active,
                ],
            )
            ->values();
    }

    /**
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it.
     *
     * It sorts by `name`, not `code`: a table is searched by code, a dropdown by
     * name.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the default
     * @param  int|null  $countryId  country to filter by; null = all
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null, ?int $countryId = null): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        $label ??= fn (self $condition): string => $condition->name;

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        if ($countryId !== null) {
            $query->where('country_id', $countryId);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $condition): array => [
                    'value' => $condition->id,
                    'label' => $label($condition),
                ],
            )
            ->all();
    }
}
