<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Saca `precio`, `stock` y `duracion` de la biblioteca de atributos.
 *
 * Decisión del 2026-08-23 tras el estudio: son campos de PRIMERA CLASE de lo que
 * adopta el negocio, no atributos genéricos. La razón es práctica y es la misma
 * por la que commercetools los pone en la variante y no en los atributos: se
 * consultan, ordenan, filtran y actualizan en masa todo el tiempo, y necesitan
 * moneda, impuesto e historial. Metidos en un jsonb genérico, "todos los platos
 * de menos de $5.000" o "subir 10% la carta" no se pueden resolver.
 *
 * `stock` además necesita transacciones y reservas concurrentes, y `duracion` la
 * consulta la agenda en cada slot.
 *
 * Se borran de verdad (no soft delete): son filas de semilla que todavía no
 * referencia nadie. Si alguna ya estuviera en uso, la baja se haría desactivando.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $codes = ['precio', 'stock', 'duracion'];

    public function up(): void
    {
        DB::table('service_attributes')->whereIn('code', $this->codes)->delete();
    }

    /**
     * Irreversible a propósito: volver a crearlas es correr el seeder de una
     * versión anterior, no deshacer un DELETE sin datos que restaurar.
     */
    public function down(): void {}
};
