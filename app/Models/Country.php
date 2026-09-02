<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Closure;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['currency_id', 'name', 'code', 'iso2', 'phone_code', 'is_active'])]
class Country extends Model implements DataTable
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public static function normalizeCode(string $value): string
    {
        return mb_strtoupper($value);
    }

    /**
     * Country names are PROPER NAMES. They are kept as typed, same criterion as
     * Currency::normalizeName; only the spacing is cleaned.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * The column is nullable, so an empty field is stored as null and not ''.
     * Otherwise half the countries would hold a dialling code that is present
     * but empty, which `whereNull` never finds.
     */
    public static function normalizePhoneCode(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', '', (string) $value));

        return $normalized === '' ? null : $normalized;
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
     * The 2-letter twin of `code`: what PHP's per-country timezone list and
     * geo-IP take. Same normalization — both are ISO 3166-1 codes.
     */
    protected function iso2(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeCode($value),
        );
    }

    protected function phoneCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizePhoneCode($value),
        );
    }

    /**
     * The `id` always travels: it is the only stable key for editing. The `code`
     * is user-editable, so it cannot identify the row.
     *
     * @return Collection<int, array{id: int, code: string, name: string, phone_code: string|null, currency: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('currency:id,code')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $country): array => [
                    'id' => $country->id,
                    'code' => $country->code,
                    'name' => $country->name,
                    'phone_code' => $country->phone_code,
                    'currency' => $country->currency?->code ?? '',
                    'active' => $country->is_active,
                ],
            )
            ->values();
    }

    /**
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it. `label` is opt-in for the same
     * reason — the default has to fit the callers that already exist.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the default
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        $label ??= fn (self $country): string => $country->code.' — '.$country->name;

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $country): array => [
                    'value' => $country->id,
                    'label' => $label($country),
                ],
            )
            ->all();
    }
}
