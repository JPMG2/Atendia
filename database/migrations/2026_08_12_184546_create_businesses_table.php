<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `businesses` son los negocios que contratan AtendIa: el TENANT.
 *
 * Todo dato operativo (conversaciones, mensajes, documentos de conocimiento)
 * cuelga de acá y se aísla por `business_id`. Un usuario pertenece a un solo
 * negocio; el admin (el dueño de AtendIa) no pertenece a ninguno.
 *
 * El país se DECLARA al registrarse. La geolocalización por IP solo pre-completa
 * el campo: sirve para elegir el idioma, no para respaldar una factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Nombre del negocio del cliente');
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('billing_email')->comment('A dónde se le manda la factura');
            $table->boolean('is_active')->default(true)->comment('Cortar el servicio sin borrar datos');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
