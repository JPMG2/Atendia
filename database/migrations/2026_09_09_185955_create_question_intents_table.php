<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The universal "what is the customer after" list every business
     * shares, whatever its sector: the stable half of a topic. Platform
     * data, not tenant data — the object half comes from each catalog.
     */
    public function up(): void
    {
        Schema::create('question_intents', function (Blueprint $table): void {
            $table->id();

            $table->string('key', 40)->unique()->comment('Clave estable que devuelve la IA (price, hours...); nunca se traduce');
            $table->string('name', 80)->comment('Cómo lo lee el dueño en las estadísticas');
            $table->string('description')->comment('Qué entra en esta intención: la guía que lee la IA para elegir');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_intents');
    }
};
