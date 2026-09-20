<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level identity: the person behind a WhatsApp number across
     * EVERY business. No business_id on purpose — this is AtendIa's own
     * aggregate layer, read only by the admin, never by a tenant.
     */
    public function up(): void
    {
        Schema::create('platform_contacts', function (Blueprint $table): void {
            $table->id();

            $table->string('phone', 30)->unique()->comment('El WhatsApp de la persona, solo dígitos; la llave que cruza negocios');
            $table->string('name')->nullable()->comment('El mejor nombre real conocido, venga del negocio que venga');
            $table->string('language', 8)->nullable()->comment('Último idioma detectado');
            $table->string('country_code', 2)->nullable()->comment('ISO2 derivado del prefijo telefónico');

            $table->unsignedInteger('businesses_count')->default(0)->comment('Con cuántos negocios habló; el pulso cross-negocio de AtendIa');
            $table->unsignedInteger('conversations_count')->default(0);
            $table->timestamp('first_seen_at')->comment('Primer mensaje a cualquier negocio');
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_contacts');
    }
};
