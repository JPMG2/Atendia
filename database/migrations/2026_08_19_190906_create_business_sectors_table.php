<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubros: la agrupación mayor de un negocio (Salud, Gastronomía, Belleza…).
 *
 * Maestro del panel admin: lo carga el dueño de AtendIa, el negocio solo elige.
 * Sirve para agrupar y reportar; lo que después maneja al asistente es la
 * ACTIVIDAD, que cuelga de acá (ver `business_activities`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_sectors', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique()->comment('Clave estable del rubro: salud, gastronomia…');
            $table->string('name')->unique()->comment('Salud, Gastronomía, Belleza');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Orden en que se le ofrece al negocio');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sectors');
    }
};
