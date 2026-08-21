<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active'])]
class Currency extends Model implements DataTable
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    use LogsActivity;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Auditoría del maestro: quién cambió qué y cuándo.
     *
     * Es un catálogo que editan administradores a mano, así que interesa el
     * rastro. `logOnlyDirty` guarda únicamente lo que cambió de verdad, y
     * `dontSubmitEmptyLogs` evita una entrada vacía cuando se guarda sin tocar
     * nada. El causante (el usuario logueado) lo resuelve spatie solo.
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
     * Los nombres de moneda son NOMBRES PROPIOS: "Dólar Estadounidense",
     * "Franco CFA", "Real Brasileño". Acá había un ucfirst(mb_strtolower()) que
     * los destrozaba —"Franco cfa"— y que además, por no ser multibyte, dejaba
     * en minúscula cualquier nombre que empezara con acento o Ñ ("Ñandú" =>
     * "ñandú"). Se respeta lo que escribe el usuario; solo se limpian espacios.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * El símbolo se respeta tal cual lo escribe el usuario. Tenía un ucfirst() que
     * lo capitalizaba arbitrariamente ("us$" => "Us$", que no es "US$") y que además
     * no es multibyte. Solo se limpian espacios, igual que en el nombre.
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
     * El `id` viaja siempre: es la única clave estable para editar. El `code` es
     * editable por el usuario, así que no sirve para identificar la fila.
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
}
