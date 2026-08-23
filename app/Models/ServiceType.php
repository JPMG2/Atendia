<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tipo de servicio: QUÉ ofrece un negocio. Consulta, Plato, Mesa, Arreglo.
 *
 * Global, no de un rubro. Hereda UNA modalidad. Qué actividades lo sugieren lo
 * dice el pivot, y sugerir nunca es permitir en exclusiva.
 */
#[Fillable(['code', 'name', 'description', 'service_modality_id', 'business_sector_id', 'sort_order', 'is_active'])]
class ServiceType extends Model implements DataTable
{
    /** @use HasFactory<ServiceTypeFactory> */
    use HasFactory;

    use LogsActivity;

    // Un maestro no se borra: el pivot y los negocios que lo adoptaron quedarían colgando.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'service_modality_id', 'business_sector_id', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalog');
    }

    /**
     * @return BelongsTo<ServiceModality, $this>
     */
    public function modality(): BelongsTo
    {
        // La FK va explícita: Eloquent la deduciría como `modality_id` a partir
        // del nombre del método, no del tipo devuelto.
        return $this->belongsTo(ServiceModality::class, 'service_modality_id');
    }

    /**
     * El rubro es SOLO agrupación de la pantalla del admin. Quién ofrece este
     * tipo lo decide {@see self::activities()}.
     *
     * @return BelongsTo<BusinessSector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(BusinessSector::class, 'business_sector_id');
    }

    /**
     * Los atributos que lleva este tipo, con lo que es propio de ESTA instancia:
     * si acá es obligatorio, en qué orden va y con qué etiqueta se muestra.
     *
     * NO se puede llamar `attributes()`: `$model->attributes` es el array
     * interno de columnas de Eloquent, y la relación quedaría tapada por él sin
     * que nada avise (devuelve un array y revienta recién al usarlo).
     *
     * @return BelongsToMany<ServiceAttribute, $this>
     */
    public function serviceAttributes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceAttribute::class, 'service_type_attribute')
            ->withPivot(['is_required', 'sort_order', 'label_override', 'hint_override'])
            ->withTimestamps()
            ->orderBy('service_type_attribute.sort_order');
    }

    /**
     * Las actividades a las que se les SUGIERE este tipo. La ausencia de una fila
     * no impide adoptarlo: el catálogo ofrece, no prohíbe.
     *
     * @return BelongsToMany<BusinessActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessActivity::class, 'activity_service_type')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_modality_id' => 'integer',
            'business_sector_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Nombre propio: se respeta lo que escribe el admin, solo se limpian espacios. */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** La clave es técnica, no copy: siempre en minúsculas. */
    public static function normalizeCode(string $value): string
    {
        return mb_strtolower(trim($value));
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
     * La fila muestra la modalidad y el rubro APLANADOS a su nombre legible, y
     * los atributos como una lista: es lo que la maqueta pinta como chips.
     *
     * @return Collection<int, array{id: int, code: string, name: string, description: string, modality: string, sector: string, attributes: list<string>, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with(['modality:id,name', 'sector:id,name', 'serviceAttributes'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $type): array => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description ?? '',
                    // El null viaja como '' (convención de la fila de catálogo).
                    'modality' => $type->modality?->name ?? '',
                    'sector' => $type->sector?->name ?? '',
                    // Con la etiqueta de ESTE tipo, no la global: en Mesa el
                    // atributo "Personas" se lee "Comensales", que es lo que va a
                    // ver el negocio. Mostrar la global acá haría que el override
                    // pareciera no estar aplicado.
                    'attributes' => $type->serviceAttributes
                        ->map(fn (ServiceAttribute $attribute): string => $attribute->pivot->label_override ?? $attribute->name)
                        ->values()
                        ->all(),
                    'order' => $type->sort_order,
                    'active' => $type->is_active,
                ],
            )
            ->values();
    }
}
