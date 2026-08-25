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

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * País al que pertenece la provincia (FK obligatoria en la tabla).
     *
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
     * Nombre propio: se respeta lo que escribe el usuario, solo se limpian
     * espacios. Mismo criterio que Country::normalizeName.
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
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
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
     * Opciones para el combobox de provincia.
     *
     * Un array vacío NO filtra: trae el catálogo completo. Ese es el default
     * porque el combobox resuelve la opción elegida buscando su id dentro de
     * `options` (resources/js/combobox.js): si una fila dada de baja quedara
     * fuera, editar un registro que la referencia mostraría el campo vacío.
     *
     * El `label` lo decide quien llama: el catálogo necesita el código del país
     * para distinguir dos provincias homónimas, un formulario de negocio no.
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

        $label ??= fn (self $province): string => $province->name.' — '.($province->country?->code ?? '—');

        if ($states !== []) {
            $query->whereIn('is_active', $states);
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
