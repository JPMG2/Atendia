<?php

declare(strict_types=1);

use App\Models\ServiceAttribute;
use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué atributos lleva cada tipo de servicio. Es el "attribute set" de Magento.
 *
 * El atributo se define UNA vez y se reutiliza; lo que cambia de un tipo a otro
 * vive acá. Drupal Field API es la referencia: separa el *storage* global (tipo
 * de dato, cardinalidad, que NO se pueden tocar desde la instancia) de la
 * *instancia* en cada bundle, que sí puede sobrescribir **etiqueta y ayuda**.
 *
 * Ese override no es un lujo: el atributo global se llama "Duración", pero en
 * Mesa tiene que decir "Tiempo de reserva" y en Consulta "Duración del turno".
 * Sin las dos columnas, el admin termina creando tres atributos distintos para
 * el mismo dato y la biblioteca deja de ser reutilizable.
 *
 * `restrictOnDelete` en los dos lados: un atributo o un tipo en uso NO se borra,
 * se desactiva. Los maestros usan SoftDeletes, y un soft delete es un UPDATE que
 * el FK ni ve — así que la protección real es que nunca haya un delete duro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_type_attribute', function (Blueprint $table): void {
            $table->id();

            $table->foreignIdFor(ServiceType::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(ServiceAttribute::class)->constrained()->restrictOnDelete();

            $table->boolean('is_required')->default(false)
                ->comment('Obligatorio EN ESTE TIPO: el mismo atributo puede ser opcional en otro');
            $table->unsignedSmallInteger('sort_order')->default(0)
                ->comment('En qué orden se le piden al negocio');

            $table->string('label_override')->nullable()
                ->comment('"Tiempo de reserva" en Mesa para el atributo global "Duración"');
            $table->string('hint_override')->nullable()
                ->comment('Ayuda propia de este tipo; si es null manda la del atributo');

            $table->timestamps();

            // Un atributo no puede estar dos veces en el mismo tipo.
            $table->unique(['service_type_id', 'service_attribute_id'], 'service_type_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_attribute');
    }
};
