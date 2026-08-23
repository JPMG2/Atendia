<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atributos: la biblioteca GLOBAL de campos que puede llevar un tipo de servicio.
 *
 * El atributo se define UNA vez y se reutiliza: `Precio` es el mismo atributo en
 * Plato y en Combo, `Duración` el mismo en Consulta, Estudio y Control. Qué
 * atributos lleva cada tipo —y si ahí son obligatorios y en qué orden— vive en el
 * pivot, no acá: el mismo atributo puede ser obligatorio en un tipo y opcional en
 * otro. Es el "attribute set" de Magento.
 *
 * `data_type` decide cómo se pinta el campo y cómo se valida el valor que carga
 * el negocio. Sus valores salen de `config/attribute_types.php` y NO de una
 * tabla: agregar un tipo de dato siempre necesita un renderer en código, así que
 * una tabla dejaría crear un tipo que no sabe dibujarse. Un tipo desconocido cae
 * a texto. Si algún día conviene moverlo a tabla, la columna ya es un string:
 * el cambio no cuesta una migración.
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
