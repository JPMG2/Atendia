<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `businesses` are the ones hiring AtendIa: the TENANT.
 *
 * Every operational record hangs off here and is isolated by `business_id`. A
 * user belongs to one business; the admin belongs to none. The country is
 * DECLARED on sign-up — geolocation only pre-fills the field, which is good
 * enough to pick a language and not to back an invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Nombre del negocio del cliente');
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete()
                ->comment('Provincia/estado del negocio: ubica el timezone y el tono local');
            $table->string('timezone', 64)->nullable()->comment('Zona IANA del negocio, para enviar mensajes en su hora local');
            $table->string('billing_email')->comment('A dónde se le manda la factura');
            $table->string('whatsapp_number', 30)->nullable()->comment('El WhatsApp del negocio que atiende la IA');
            $table->string('fallback_whatsapp_number', 30)->nullable()->comment('WhatsApp de una persona: acá se derivan los mensajes que la IA no puede responder');
            $table->string('email')->nullable()->comment('Correo de contacto del negocio: acá llega la bienvenida y el contacto público');
            $table->string('web')->nullable()->comment('Sitio web del negocio, para su perfil público');
            $table->string('logo_path')->nullable()->comment('Logo del negocio, uno solo para ambos temas');
            $table->string('address')->nullable()->comment('Calle y número del local, si atiende en uno');
            $table->string('city')->nullable()->comment('Ciudad del local: la provincia sola no lo ubica');
            $table->boolean('has_premises')->nullable()->comment('¿Atiende en un local? Null = todavía no respondió (patrón GBP)');
            $table->text('description')->nullable()->comment('Presentación corta del negocio: la IA la usa para presentarse y responder mejor');
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete()
                ->comment('Moneda legal en la que publica sus precios');
            $table->foreignId('reference_currency_id')->nullable()->constrained('currencies')->restrictOnDelete()
                ->comment('Moneda de referencia mostrada junto al precio (el "Ref" venezolano: Bs por ley, USD real)');
            $table->foreignId('tax_condition_id')->nullable()->constrained()->restrictOnDelete()
                ->comment('Condición fiscal del negocio; null = persona natural sin datos fiscales, válido');
            $table->string('tax_id', 20)->nullable()->comment('Número de identificación fiscal (RIF / CUIT), si lo tiene');
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
