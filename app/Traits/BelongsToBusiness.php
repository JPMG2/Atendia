<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Business;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla el modelo por negocio.
 *
 * Hace las DOS mitades, y las dos hacen falta:
 *   - filtra toda consulta por el negocio actual;
 *   - completa `business_id` al crear.
 *
 * Sin la segunda, el registro nace sin dueño y queda invisible para todos. Con
 * la columna en NOT NULL eso explota en la base, que es la falla que querés:
 * ruidosa y en el momento, no un dato huérfano descubierto tres meses después.
 *
 * El filtro se aplica SOLO cuando hay un negocio actual. Sin negocio no filtra,
 * y eso es a propósito: es el admin y son los procesos de fondo. Ver {@see Tenant}.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder): void {
            $businessId = app(Tenant::class)->id();

            if ($businessId === null) {
                return;
            }

            // qualifyColumn: sin el nombre de la tabla, un join contra otra tabla
            // que también tenga `business_id` deja la consulta ambigua.
            $builder->where($builder->getModel()->qualifyColumn('business_id'), $businessId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('business_id') === null) {
                $model->setAttribute('business_id', app(Tenant::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
