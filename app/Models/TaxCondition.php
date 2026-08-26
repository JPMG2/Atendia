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

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
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
     * Nombre propio ("Responsable Inscripto"): se respeta lo que escribe el
     * usuario, solo se limpian espacios.
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
     * El `id` viaja siempre: es la única clave estable para editar. El `code` es
     * editable por el usuario, así que no sirve para identificar la fila.
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
     * Opciones para el combobox de condición fiscal.
     *
     * Un array vacío NO filtra: trae el catálogo completo. Ese es el default
     * porque el combobox resuelve la opción elegida buscando su id dentro de
     * `options` (resources/js/combobox.js): si una fila dada de baja quedara
     * fuera, editar un registro que la referencia mostraría el campo vacío.
     *
     * El default es el nombre pelado ("Responsable Inscripto"): es lo que el
     * usuario reconoce. Una pantalla que necesite el código puede pasar su
     * propio `label`, sin tocar a los demás llamadores.
     *
     * Ordena por `name` y no por `code` como la tabla del maestro: en una tabla
     * se busca por código, en un desplegable se busca por nombre.
     *
     * @param  list<bool>  $states  estados de `is_active` a incluir; vacío = todos
     * @param  (Closure(self): string)|null  $label  texto de la opción; null = el default
     * @param  int|null  $countryId  ID del país a filtrar; null = todos
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
