<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades: el oficio concreto del negocio (Farmacia, Panadería, Peluquería).
 *
 * Es el nivel FINO del rubro y el que de verdad le habla a la IA: de la
 * actividad van a colgar el tono del asistente, qué datos pide para agendar o
 * tomar un pedido y el paquete de conocimiento semilla del oficio. Por eso el
 * `code` es único GLOBAL: es la clave con la que se busca ese perfil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_activities', function (Blueprint $table): void {
            $table->id();
            // Restrictivo a propósito: un rubro con actividades cargadas no se
            // borra en silencio. Mismo criterio que el resto de los maestros.
            $table->foreignIdFor(BusinessSector::class)->constrained()->restrictOnDelete();
            $table->string('code', 40)->unique()->comment('Clave estable del oficio: farmacia, panaderia…');
            $table->string('name')->comment('Farmacia, Panadería, Peluquería');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // El nombre es único DENTRO del rubro, no a nivel global: dos rubros
            // distintos pueden ofrecer una actividad que se llame igual.
            $table->unique(['business_sector_id', 'name']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_activities');
    }
};
