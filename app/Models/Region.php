<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['province_id', 'name', 'is_active'])]
class Region extends Model implements DataTable
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Provincia a la que pertenece la región (FK obligatoria en la tabla).
     *
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'province_id' => 'integer',
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
     * La región cuelga de una provincia y la provincia de un país. El país viaja
     * en la fila —y no solo la provincia— porque si no hay que saberse de memoria
     * a qué país pertenece cada provincia para entender la lista.
     *
     * `country_id` va en el select de la provincia a propósito: sin esa columna
     * Eloquent no puede resolver el `belongsTo` al país y `country` volvería vacío.
     *
     * @return Collection<int, array{id: int, name: string, province: string, country: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with(['province:id,name,country_id', 'province.country:id,name'])
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $region): array => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'province' => $region->province?->name ?? '',
                    'country' => $region->province?->country?->name ?? '',
                    'active' => $region->is_active,
                ],
            )
            ->values();
    }
}
