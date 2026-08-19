<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría y control de cambios del negocio.
 *
 * Complementa a spatie/activitylog (que guarda el rastro completo en
 * `activity_log`): estas columnas dejan mostrar quién creó o tocó el registro
 * sin salir a cruzar el log, y sobreviven a una purga del log.
 *
 * Un negocio NUNCA se borra de verdad: se da de baja. Por eso `softDeletes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // Si se borra el usuario, el negocio queda: solo se pierde el autor.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
