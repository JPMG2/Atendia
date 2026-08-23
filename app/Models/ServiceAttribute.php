<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Atributo de servicio: un campo REUTILIZABLE que un tipo de servicio puede llevar.
 *
 * `Precio` es el mismo atributo en Plato y en Combo. Qué tipo lo lleva —y si ahí
 * es obligatorio— vive en el pivot, no acá.
 */
#[Fillable(['code', 'name', 'description', 'data_type', 'unit', 'options', 'is_multiple', 'sort_order', 'is_active'])]
class ServiceAttribute extends Model implements DataTable
{
    /** @use HasFactory<ServiceAttributeFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;
    use TracksUserActions;

    /** Tipo al que cae un `data_type` que el sistema no conoce. */
    public const string FALLBACK_DATA_TYPE = 'text';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'data_type', 'unit', 'options', 'is_multiple', 'sort_order', 'is_active'])
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
            'options' => 'array',
            'is_multiple' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Los tipos de servicio que ya usan este atributo.
     *
     * @return BelongsToMany<ServiceType, $this>
     */
    public function types(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'service_type_attribute')
            ->withPivot(['is_required', 'sort_order', 'label_override', 'hint_override'])
            ->withTimestamps();
    }

    /**
     * ¿Algún tipo de servicio ya lo usa?
     *
     * Marca la frontera de lo que se puede tocar: mientras nadie lo use, el
     * atributo es libre; después, cambiarle el tipo de dato rompería los valores
     * ya cargados. Es también la razón por la que no se borra sino que se
     * desactiva.
     */
    public function isInUse(): bool
    {
        return $this->types()->exists();
    }

    /**
     * Los tipos de dato disponibles, con su etiqueta.
     *
     * @return array<string, string>
     */
    public static function dataTypes(): array
    {
        return config('attribute_types');
    }

    /**
     * La etiqueta legible del tipo. Un tipo que ya no está en config —porque se
     * lo quitó después de haberlo usado— cae a texto en vez de dejar la celda
     * vacía y hacer creer que el atributo no tiene tipo.
     */
    public static function dataTypeLabel(string $dataType): string
    {
        $types = self::dataTypes();

        return $types[$dataType] ?? $types[self::FALLBACK_DATA_TYPE];
    }

    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public static function normalizeCode(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Las opciones se escriben en un solo campo separadas por coma. Se limpian
     * los espacios sobrantes y se descartan las vacías: "Chico, , Grande" no
     * puede meter una opción en blanco en la lista que después ve el negocio.
     *
     * @param  string|array<int, string>|null  $value
     * @return array<int, string>|null
     */
    public static function normalizeOptions(string|array|null $value): ?array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        $items = array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), $items),
            static fn (string $item): bool => $item !== '',
        ));

        return $items === [] ? null : $items;
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
     * Cómo se describe el tipo de dato en una sola celda: "Número · min · varios".
     *
     * El tipo, la unidad y la cardinalidad son tres datos chicos que juntos se
     * leen de un vistazo; en tres columnas separadas ocupaban un tercio de la
     * tabla y la empujaban fuera del panel. No se abrevia nada: se agrupa.
     */
    public static function describeType(self $attribute): string
    {
        $parts = [self::dataTypeLabel($attribute->data_type)];

        if ($attribute->unit !== null && $attribute->unit !== '') {
            $parts[] = $attribute->unit;
        }

        if ($attribute->is_multiple) {
            $parts[] = __('catalog.service_attribute.multiple.on');
        }

        return implode(' · ', $parts);
    }

    /**
     * @return Collection<int, array{id: int, code: string, name: string, description: string, type: string, options: list<string>, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $attribute): array => [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'description' => $attribute->description ?? '',
                    // La fila muestra la ETIQUETA, no la clave: el admin lee
                    // "Lista de opciones", no "list".
                    'type' => self::describeType($attribute),
                    // Lista de verdad, no un string con comas: la celda las pinta
                    // como pastillas y el buscador igual las encuentra.
                    'options' => $attribute->options ?? [],
                    'order' => $attribute->sort_order,
                    'active' => $attribute->is_active,
                ],
            )
            ->values();
    }
}
