<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cardinalidad del atributo: ¿admite un valor o varios?
 *
 * "Obra social" no es una: un consultorio acepta OSDE, Swiss Medical y Galeno.
 * "Zona de cobertura" tampoco. Sin esto el negocio los escribe apretados en un
 * string y el asistente no puede filtrar por cobertura.
 *
 * Es una columna booleana HOY y migrar todos los valores cargados MAÑANA, así que
 * entra antes de que exista el primer valor. Drupal la llama `cardinality`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_attributes', function (Blueprint $table): void {
            $table->boolean('is_multiple')->default(false)->after('options')
                ->comment('Si admite varios valores a la vez (obras sociales, zonas)');
        });
    }

    public function down(): void
    {
        Schema::table('service_attributes', function (Blueprint $table): void {
            $table->dropColumn('is_multiple');
        });
    }
};
