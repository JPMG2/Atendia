<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría y control de cambios en los maestros del panel de Catálogos.
 *
 * Los maestros los editan administradores a mano, así que interesa quién tocó
 * qué. Las columnas dejan mostrar el autor en la grilla sin cruzar el log
 * (`activity_log` sigue guardando el detalle de los valores).
 *
 * `softDeletes` porque un maestro NO se borra de verdad: si se borrara la fila,
 * todo lo que la referencia —un país con provincias, una actividad con
 * negocios— quedaría colgando. `regions` ya tenía la columna.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'currencies',
        'countries',
        'provinces',
        'regions',
        'tax_conditions',
        'social_networks',
        'current_statuses',
        'business_sectors',
        'business_activities',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                // Si se borra el usuario, el maestro queda: solo se pierde el autor.
                $blueprint->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropConstrainedForeignId('created_by');
                $blueprint->dropConstrainedForeignId('updated_by');
                $blueprint->dropConstrainedForeignId('deleted_by');

                // `regions` traía la columna de antes de esta migración.
                if ($table !== 'regions') {
                    $blueprint->dropSoftDeletes();
                }
            });
        }
    }
};
