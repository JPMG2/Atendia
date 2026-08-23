<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * El negocio que contrata AtendIa: el TENANT.
 *
 * No confundir con {@see Company}, que es AtendIa misma (el emisor de la
 * factura, un único registro). Todo dato operativo del cliente cuelga de acá.
 */
#[Fillable(['name', 'country_id', 'billing_email', 'is_active'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use LogsActivity;

    // Un negocio no se borra: se da de baja. Los datos operativos que le cuelgan
    // (usuarios, conocimiento) tienen que seguir siendo rastreables.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Auditoría del tenant: quién cambió qué y cuándo.
     *
     * Las columnas `created_by`/`updated_by`/`deleted_by` de la tabla son el
     * atajo para mostrar el autor en pantalla; el rastro COMPLETO (valores
     * viejos y nuevos) vive acá, en `activity_log`. Se excluyen esas columnas
     * del log: el causante ya lo resuelve spatie solo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'country_id', 'billing_email', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('business');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Las actividades que declaró el negocio: la principal primero.
     *
     * Son varias a propósito. La panadería que además pone mesas suma
     * "Cafetería" y con eso empieza a ver los tipos de servicio del salón — sin
     * ninguna excepción en el código y sin que nadie le desbloquee nada a mano.
     *
     * @return BelongsToMany<BusinessActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessActivity::class, 'activity_business')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByDesc('activity_business.is_primary')
            ->orderBy('activity_business.sort_order');
    }

    /**
     * La actividad PRINCIPAL, o null si el negocio todavía no eligió ninguna.
     *
     * Es la que manda para el tono del asistente, el paquete de conocimiento del
     * oficio y los reportes. Método y no relación: devuelve un modelo suelto, y
     * como propiedad (`$business->primaryActivity`) Eloquent buscaría una
     * relación y tiraría error. Si la colección ya está cargada la usa, para no
     * disparar una consulta por negocio en una grilla.
     */
    public function primaryActivity(): ?BusinessActivity
    {
        if ($this->relationLoaded('activities')) {
            return $this->activities->firstWhere('pivot.is_primary', true);
        }

        return $this->activities()->wherePivot('is_primary', true)->first();
    }

    /**
     * Deja las actividades del negocio en exactamente esto.
     *
     * Una sola principal —lo garantiza además un índice único parcial en la
     * base— y las secundarias en el orden en que llegan. Pasar la principal
     * dentro de las secundarias no la duplica: se ignora.
     *
     * @param  list<int>  $secondaryIds
     */
    public function syncActivities(?int $primaryId, array $secondaryIds = []): void
    {
        $pivot = [];

        if ($primaryId !== null) {
            $pivot[$primaryId] = ['is_primary' => true, 'sort_order' => 0];
        }

        $order = 0;

        foreach ($secondaryIds as $id) {
            if ($id === $primaryId || isset($pivot[$id])) {
                continue;
            }

            $pivot[$id] = ['is_primary' => false, 'sort_order' => ++$order];
        }

        $this->activities()->sync($pivot);
    }

    /**
     * Los tipos de servicio que se le SUGIEREN a este negocio: la unión de lo que
     * sugiere cada una de sus actividades.
     *
     * Unión y no intersección: la panadería-cafetería tiene que ver lo de las
     * dos. Y sigue siendo una sugerencia — nada impide adoptar un tipo que no
     * esté acá, es lo que se muestra arriba y no lo que se permite.
     *
     * @return Collection<int, ServiceType>
     */
    public function suggestedServiceTypes(): Collection
    {
        return ServiceType::query()
            ->whereHas(
                'activities',
                fn (Builder $query): Builder => $query->whereIn(
                    'business_activities.id',
                    $this->activities()->select('business_activities.id'),
                ),
            )
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }
}
