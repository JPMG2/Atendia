<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * El negocio que contrata AtendIa: el TENANT.
 *
 * No confundir con {@see Company}, que es AtendIa misma (el emisor de la
 * factura, un único registro). Todo dato operativo del cliente cuelga de acá.
 */
#[Fillable(['name', 'country_id', 'business_activity_id', 'billing_email', 'is_active'])]
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
            ->logOnly(['name', 'country_id', 'business_activity_id', 'billing_email', 'is_active'])
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
            'business_activity_id' => 'integer',
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
     * Actividad que declaró el negocio. Nullable: los negocios cargados antes
     * del maestro todavía no eligieron una.
     *
     * @return BelongsTo<BusinessActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(BusinessActivity::class, 'business_activity_id');
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
