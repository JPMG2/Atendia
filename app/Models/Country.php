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
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['currency_id', 'name', 'code', 'iso2', 'phone_code', 'is_active'])]
class Country extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /** A country is the root of an address tree and of the tax and holiday data: all of it blocks. */
    public function usage(): array
    {
        return $this->onlyHeld([
            'provinces' => $this->heldBy('provinces', 'country_id'),
            'tax_conditions' => $this->heldBy('tax_conditions', 'country_id'),
            'country_holidays' => $this->heldBy('country_holidays', 'country_id'),
            'businesses' => $this->heldBy('businesses', 'country_id'),
        ]);
    }

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

    /**
     * @return HasMany<Province, $this>
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    /**
     * @return HasMany<CountryHoliday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(CountryHoliday::class);
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
     * The two counts say at a glance which country is still missing its provinces or its holidays.
     *
     * @return Collection<int, array{id: int, code: string, iso2: string, name: string, phone_code: string|null, currency: string, provinces: int, holidays: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('currency:id,code')
            ->withCount(['provinces', 'holidays'])
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $country): array => [
                    'id' => $country->id,
                    'code' => $country->code,
                    'iso2' => $country->iso2,
                    'name' => $country->name,
                    'phone_code' => $country->phone_code,
                    'currency' => $country->currency?->code ?? '',
                    'provinces' => (int) $country->provinces_count,
                    'holidays' => (int) $country->holidays_count,
                    'active' => $country->is_active,
                ],
            )
            ->values();
    }

    /** The 3-letter code is the visible key and is searched with the name; the 2-letter one is not. */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('code', __('catalog.country.columns.code'), CatalogCell::Code, search: true),
            new CatalogColumnDto('iso2', __('catalog.country.columns.iso2'), CatalogCell::Code),
            new CatalogColumnDto('name', __('catalog.country.columns.name'), CatalogCell::Name, fill: true, search: true),
            new CatalogColumnDto('phone_code', __('catalog.country.columns.phone_code'), CatalogCell::Symbol),
            new CatalogColumnDto('currency', __('catalog.country.columns.currency'), CatalogCell::Symbol),
            new CatalogColumnDto('provinces', __('catalog.country.columns.provinces'), CatalogCell::Number),
            new CatalogColumnDto('holidays', __('catalog.country.columns.holidays'), CatalogCell::Number),
            new CatalogColumnDto('active', __('catalog.country.columns.status'), CatalogCell::Active, on: __('catalog.country.status.active'), off: __('catalog.country.status.inactive')),
        ];
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

    /**
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @return array<int, array{code: string, flag: string, name: string}>
     */
    public static function phoneFlags(array $states = []): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        return $query->orderBy('name')->get(['iso2', 'phone_code', 'name'])
            ->map(fn (self $country): array => [
                'name' => (string) $country->name,
                'code' => (string) $country->phone_code,
                'flag' => implode('', array_map(
                    fn (string $letter): string => mb_chr(0x1F1E6 - 65 + ord($letter)),
                    str_split(strtoupper((string) $country->iso2)),
                )),
            ])
            ->all();
    }

    /** A nullable id lets callers pass an unset form value without checking first. */
    public static function dialCode(?int $id): ?string
    {
        $code = self::query()->find($id)?->phone_code;

        return $code === null ? null : (string) $code;
    }

    /**
     * A stored phone as the web's two boxes hold it: the dial code and the number.
     * A value with no dial gets the default one.
     *
     * @return array{dial: string|null, number: string}
     */
    public static function splitPhone(?string $stored, ?string $defaultDial): array
    {
        $stored = trim((string) $stored);

        if (preg_match('/^\+(\d{1,4})\s+(.+)$/', $stored, $parts) === 1) {
            return ['dial' => $parts[1], 'number' => $parts[2]];
        }

        // Older numbers were typed as they came: "+584247673951" or "584247673951" carry their own
        // dial code (the longest known one wins, as on the web), "04247673951" is national.
        $digits = preg_replace('/\D/', '', $stored) ?? '';
        $international = str_starts_with($stored, '+') || ($defaultDial !== null && str_starts_with($digits, $defaultDial) && strlen($digits) >= strlen($defaultDial) + 7);

        if ($international) {
            $dial = collect(self::phoneFlags())
                ->pluck('code')
                ->map(fn (string $code): string => ltrim($code, '+'))
                ->filter(fn (string $code): bool => $code !== '' && str_starts_with($digits, $code))
                ->sortByDesc(fn (string $code): int => strlen($code))
                ->first();

            if ($dial !== null) {
                return ['dial' => $dial, 'number' => substr($digits, strlen($dial))];
            }
        }

        return ['dial' => $defaultDial, 'number' => ltrim($stored, '+')];
    }

    /**
     * The country behind a raw WhatsApp number. Longest dial-code prefix
     * wins, so 1-268… resolves the island and not the whole NANP.
     */
    public static function iso2FromPhone(string $digits): ?string
    {
        if ($digits === '') {
            return null;
        }

        return self::query()
            ->whereNotNull('phone_code')
            ->get(['iso2', 'phone_code'])
            ->filter(fn (self $country): bool => str_starts_with($digits, ltrim((string) $country->phone_code, '+')))
            ->sortByDesc(fn (self $country): int => mb_strlen((string) $country->phone_code))
            ->first()
            ?->iso2;
    }
}
