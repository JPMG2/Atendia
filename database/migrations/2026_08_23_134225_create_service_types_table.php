<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use App\Models\ServiceModality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de servicio: QUÉ ofrece un negocio. Consulta, Plato, Mesa, Arreglo.
 *
 * El tipo es GLOBAL, no de un rubro: "Consulta" le sirve al Doctor, al
 * Odontólogo y al Laboratorio. Quién lo SUGIERE lo dice `activity_service_type`,
 * y esa sugerencia nunca es una prohibición — el negocio puede adoptar cualquier
 * tipo (patrón Google Business Profile: la categoría es una lente, no una jaula).
 *
 * Hereda UNA modalidad, de muchos a uno: Plato y Combo son dos tipos distintos
 * que comparten "Producto". Lo que NO existe es un tipo con dos modalidades; ese
 * caso se parte en dos tipos o lo secundario baja a atributo.
 *
 * OJO con `business_sector_id`: es **solo agrupación de la pantalla del admin**
 * (el encabezado "Salud" / "Gastronomía" de la maqueta). NO decide a quién se le
 * ofrece el tipo — eso es siempre el pivot de actividades. Sin esta columna la
 * pantalla no se puede agrupar hasta que existan actividades cargadas. Es
 * nullable a propósito: un tipo transversal no pertenece a ningún rubro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique()->comment('Clave estable que referencian el asistente y el RAG');
            $table->string('name')->unique()->comment('Consulta, Plato, Mesa, Arreglo');
            $table->string('description')->nullable();

            // Restrict: una modalidad con tipos colgando no se saca de circulación
            // borrándola, se desactiva.
            $table->foreignIdFor(ServiceModality::class)->constrained()->restrictOnDelete();

            // Agrupación de la PANTALLA, no permiso. Ver el comentario de arriba.
            $table->foreignIdFor(BusinessSector::class)->nullable()->constrained()->nullOnDelete()
                ->comment('Solo agrupa la pantalla del admin; la oferta la decide activity_service_type');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
