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

#[Fillable(['currency_id', 'name', 'code', 'phone_code', 'is_active'])]
class Country extends Model implements DataTable
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
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
     * Moneda del país (FK obligatoria en la tabla).
     *
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
     * Los nombres de país son NOMBRES PROPIOS: "República Dominicana", "El
     * Salvador", "Costa Rica". Se respeta lo que escribe el usuario (mismo
     * criterio que Currency::normalizeName); solo se limpian espacios.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * La columna es nullable: un campo vacío se guarda como null, no como ''.
     * Si no, la mitad de los países quedarían con un código telefónico "vacío
     * pero presente" y `whereNull` no los encontraría nunca.
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

    protected function phoneCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizePhoneCode($value),
        );
    }

    /**
     * El `id` viaja siempre: es la única clave estable para editar. El `code` es
     * editable por el usuario, así que no sirve para identificar la fila.
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
     * Un array vacío NO filtra: trae el catálogo completo. Ese es el default
     * porque el combobox resuelve la opción elegida buscando su id dentro de
     * `options` (resources/js/combobox.js): si una fila dada de baja quedara
     * fuera, editar un registro que la referencia mostraría el campo vacío.
     *
     * El `label` lo decide quien llama: el catálogo antepone el código para
     * poder buscar por él, un formulario de negocio quiere el nombre pelado.
     * Sin argumento sale el texto de siempre, así que sumar una variante no
     * obliga a recorrer los llamadores existentes.
     *
     * @param  list<bool>  $states  estados de `is_active` a incluir; vacío = todos
     * @param  (Closure(self): string)|null  $label  texto de la opción; null = el default
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
