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
