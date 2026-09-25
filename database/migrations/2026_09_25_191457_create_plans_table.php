<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ONE source of every plan figure: the landing cards, "Mi plan",
     * billing and every gate read these rows, so two screens can never
     * disagree on a price or a cap. Platform data, not tenant data.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();

            $table->string('code', 20)->unique()->comment('Clave estable: emprende, negocio, premium');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Orden de la escalera, del piso al techo');
            $table->unsignedInteger('price')->comment('Precio mensual en USD');
            $table->unsignedInteger('conversations_per_month');
            $table->unsignedSmallInteger('whatsapp_numbers');
            $table->unsignedSmallInteger('messages_per_hour')->comment('Ritmo máximo por contacto');
            $table->unsignedInteger('audio_minutes_per_month')->default(0)->comment('0 = sin notas de voz');
            $table->string('statistics', 20)->default('counts')->comment('counts | patterns | trends');
            $table->unsignedInteger('ask_per_month')->default(0)->comment('Consultas a Pregúntale a AtendIa; 0 = sin asistente');
            $table->unsignedSmallInteger('trial_days')->nullable()->comment('Solo el plan de la prueba gratis');
            $table->boolean('is_featured')->default(false)->comment('El "Más elegido" de las fichas');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
