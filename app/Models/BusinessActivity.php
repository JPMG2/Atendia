<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\BusinessActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Actividad del negocio: Farmacia, Panadería, Peluquería…
 *
 * Es lo que el negocio elige y lo que después define cómo atiende el asistente.
 */
#[Fillable(['business_sector_id', 'code', 'name', 'description', 'sort_order', 'is_active'])]
class BusinessActivity extends Model implements DataTable
{
    /** @use HasFactory<BusinessActivityFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Rubro al que pertenece la actividad (FK obligatoria en la tabla).
     *
     * @return BelongsTo<BusinessSector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(BusinessSector::class, 'business_sector_id');
    }

    /**
     * @return BelongsToMany<Business, $this>
     */
    /**
     * Los tipos de servicio que se le SUGIEREN a esta actividad.
     *
     * Sugerir no es permitir en exclusiva: un negocio puede adoptar un tipo que
     * no está acá (la panadería que decide poner mesas). La ausencia de una fila
     * significa "no se lo muestro arriba", nunca "no puede".
     *
     * @return BelongsToMany<ServiceType, $this>
     */
    public function suggestedServiceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'activity_service_type')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('activity_service_type.sort_order');
    }

    /**
     * Los negocios que declararon esta actividad, principal o secundaria.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'activity_business')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_sector_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => BusinessSector::normalizeName($value),
        );
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => BusinessSector::normalizeCode($value),
        );
    }

    /**
     * Se agrupan por rubro y dentro de cada uno por su orden, que es como el
     * negocio las va a ver al elegir.
     *
     * @return Collection<int, array{id: int, code: string, name: string, sector: string, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()->with('sector:id,name,sort_order')
            ->get()
            ->sortBy([fn (self $a, self $b): int => ($a->sector?->sort_order ?? 0) <=> ($b->sector?->sort_order ?? 0), fn (self $a, self $b): int => $a->sort_order <=> $b->sort_order, fn (self $a, self $b): int => strcmp($a->name, $b->name)])
            ->map(
                fn (self $activity): array => [
                    'id' => $activity->id,
                    'code' => $activity->code,
                    'name' => $activity->name,
                    'sector' => $activity->sector?->name ?? '',
                    'order' => $activity->sort_order,
                    'active' => $activity->is_active,
                ],
            )
            ->values();
    }
}
