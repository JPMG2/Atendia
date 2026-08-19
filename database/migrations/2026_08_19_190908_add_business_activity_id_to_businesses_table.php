<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El negocio declara SU actividad. La flecha va del negocio a la actividad:
 * una actividad tiene muchos negocios, un negocio tiene una sola.
 *
 * No se guarda el rubro aparte: la actividad ya sabe a cuál pertenece, y tener
 * las dos columnas abre la puerta a que se contradigan.
 *
 * Nullable porque los negocios ya cargados todavía no eligieron actividad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignIdFor(BusinessActivity::class)
                ->nullable()
                ->after('country_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_activity_id');
        });
    }
};
