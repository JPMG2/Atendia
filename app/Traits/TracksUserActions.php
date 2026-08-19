<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Deja el autor de cada cambio EN LA PROPIA FILA.
 *
 * No reemplaza a spatie/activitylog, que guarda el rastro completo —valores
 * viejos y nuevos— en `activity_log`. Esto es el atajo: poder mostrar "creado
 * por Fulano" en una grilla sin cruzar el log, y que el dato siga ahí aunque el
 * log se purgue.
 *
 * Solo escribe cuando HAY usuario logueado. Seeders, comandos y colas corren sin
 * sesión: ahí la columna queda como está en vez de pisarse con null, porque un
 * autor borrado es peor que un autor ausente.
 *
 * Requiere las columnas `created_by`, `updated_by` y `deleted_by` en la tabla.
 * `deleted_by` solo tiene sentido junto a `softDeletes`.
 */
trait TracksUserActions
{
    public static function bootTracksUserActions(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            // ??=: si la acción ya declaró el autor a mano, manda ella.
            $model->created_by ??= $userId;
            $model->updated_by ??= $userId;
        });

        static::updating(function (Model $model): void {
            if (Auth::id() !== null) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function (Model $model): void {
            // En un forceDelete la fila desaparece: sellar el autor no tiene a
            // quién servirle y encima dispara un UPDATE al pedo.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            if (Auth::id() === null) {
                return;
            }

            // saveQuietly: el borrado ya emite sus propios eventos; sin esto la
            // grabación del sello dispara `updating`/`updated` de más.
            $model->deleted_by = Auth::id();
            $model->saveQuietly();
        });

        static::restored(function (Model $model): void {
            // Un registro restaurado no tiene quién lo borró: si el sello queda,
            // la pantalla muestra un borrador de algo que está vivo.
            $model->deleted_by = null;
            $model->saveQuietly();
        });
    }

    /**
     * Usuario que dio de alta el registro. Null si lo creó un seeder o un
     * proceso de fondo, o si esa cuenta se eliminó después.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
