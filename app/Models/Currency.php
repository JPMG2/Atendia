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
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active'])]
class Currency extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    use LogsActivity;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /** A business holds it as its own currency or as the one it compares against. */
    public function usage(): array
    {
        $businesses = DB::table('businesses')
            ->where(fn ($query) => $query
                ->where('currency_id', $this->getKey())
                ->orWhere('reference_currency_id', $this->getKey()))
            ->count();

        return $this->onlyHeld([
            'countries' => $this->heldBy('countries', 'currency_id'),
            'businesses' => $businesses,
        ]);
    }

    /**
     * Master audit: who changed what, and when.
     *
     * Admins edit this catalog by hand, so the trail matters. `logOnlyDirty`
     * keeps only what really changed and `dontSubmitEmptyLogs` avoids an empty
     * entry when someone saves without touching anything.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'symbol', 'decimal_places', 'is_active'])
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
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(string $value): string
    {
        return mb_strtoupper($value);
    }

    /**
     * Currency names are PROPER NAMES. An ucfirst(mb_strtolower()) used to wreck
     * them, and being non-multibyte it also lowercased any name starting with an
     * accent. They are kept as typed; only the spacing is cleaned.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * The symbol is kept exactly as typed. An ucfirst() used to capitalise it at
     * random — "us$" became "Us$", which is not "US$" — and it is not multibyte
     * either. Only the spacing is cleaned, same as the name.
     */
    public static function normalizeSymbol(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
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

    protected function symbol(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeSymbol($value),
        );
    }

    /**
     * The `id` always travels: it is the only stable key for editing. The `code`
     * is user-editable, so it cannot identify the row.
     *
     * @return Collection<int, array{id: int, code: string, name: string, symbol: string, decimals: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('code')
            ->get()
            ->map(
                fn (self $currency): array => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimals' => $currency->decimal_places,
                    'active' => $currency->is_active,
                ],
            )
            ->values();
    }

    /** The code is what a person looks a currency up by, so it is searched with the name and the symbol is not. */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('code', __('catalog.currency.columns.code'), CatalogCell::Code, search: true),
            new CatalogColumnDto('name', __('catalog.currency.columns.name'), CatalogCell::Name, fill: true, search: true),
            new CatalogColumnDto('symbol', __('catalog.currency.columns.symbol'), CatalogCell::Symbol),
            new CatalogColumnDto('decimals', __('catalog.currency.columns.decimals'), CatalogCell::Number),
            new CatalogColumnDto('active', __('catalog.currency.columns.status'), CatalogCell::Active, on: __('catalog.currency.status.active'), off: __('catalog.currency.status.inactive')),
        ];
    }

    /**
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
            ->orderBy('code')
            ->get()
            ->map(
                fn (self $currency): array => [
                    'value' => $currency->id,
                    'label' => $currency->code.' — '.$currency->name,
                ],
            )
            ->all();
    }
}
