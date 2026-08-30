<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attributes: the GLOBAL library of fields a service type can carry.
 *
 * An attribute is defined ONCE and reused; which types carry it and how lives
 * in the pivot, since the same one can be required in one and optional in
 * another. `data_type` comes from config and not a table: a new type needs a
 * renderer in code, so a table would allow one that cannot draw itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique()->comment('Clave estable: duracion, precio, apto_celiaco');
            $table->string('name')->unique()->comment('Duración, Precio, Apto celíaco');
            $table->string('description')->nullable()->comment('Ayuda para el negocio al completarlo');
            $table->string('data_type', 20)->default('text')->comment('Clave de config/attribute_types.php');
            $table->string('unit', 15)->nullable()->comment('min, kg, personas — se muestra junto al valor');
            $table->jsonb('options')->nullable()->comment('Opciones cuando data_type es lista');
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
        Schema::dropIfExists('service_attributes');
    }
};
