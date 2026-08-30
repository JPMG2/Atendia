<?php

declare(strict_types=1);

use App\Models\ServiceAttribute;
use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which attributes each service type carries — Magento's "attribute set".
 *
 * The attribute is defined ONCE; what changes per type lives here. Following
 * Drupal's Field API, global storage stays untouchable from the instance,
 * which can still override label and help text — without that, an admin ends
 * up creating three attributes for one fact.
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

            // An attribute cannot sit twice on the same type.
            $table->unique(['service_type_id', 'service_attribute_id'], 'service_type_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_attribute');
    }
};
