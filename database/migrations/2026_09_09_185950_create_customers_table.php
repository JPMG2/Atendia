<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The business's own customer record, auto-born from the first WhatsApp
     * message. Tenant data: RLS + BelongsToBusiness fence it per business.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_contact_id')->nullable()->constrained()->nullOnDelete()->comment('La misma persona a nivel plataforma; solo el admin la cruza');

            $table->string('phone', 30)->comment('La identidad del cliente final en este negocio, solo dígitos');
            $table->string('profile_name')->nullable()->comment('El pushName de WhatsApp; se refresca con cada mensaje');
            $table->string('name')->nullable()->comment('Nombre curado (dueño o IA con confianza alta); manda sobre profile_name');
            $table->string('email')->nullable();
            $table->string('country_code', 2)->nullable()->comment('ISO2 derivado del prefijo telefónico');
            $table->string('language', 8)->nullable();
            $table->date('birthday')->nullable()->comment('Capturado en la charla; alimenta la felicitación automática');

            $table->text('notes')->nullable()->comment('Notas del dueño; la IA jamás escribe acá');
            $table->jsonb('custom_attributes')->nullable();
            $table->jsonb('ai_extracted')->nullable()->comment('Datos extraídos por la IA con confianza y origen; nunca pisan lo humano');

            $table->timestamp('marketing_opt_in_at')->nullable()->comment('Sin esta fecha no hay campañas: el opt-in es un acto explícito');
            $table->timestamp('marketing_opt_in_requested_at')->nullable()->comment('Cuándo se le pidió permiso; el asistente sella el sí');
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('first_seen_at')->comment('Primer mensaje a este negocio');
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('conversations_count')->default(0);

            $table->timestamps();

            $table->unique(['business_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
