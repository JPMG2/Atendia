<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the customer is after, in three layers: universal (no activity),
     * the trade's own (an activity, generated once by the AI) and proposed
     * (one business saw it; shared once enough businesses do). Platform
     * data, not tenant data — the object half comes from each catalog.
     */
    public function up(): void
    {
        Schema::create('question_intents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_activity_id')->nullable()->constrained()->cascadeOnDelete()->comment('Null = universal; con valor = propia de ese oficio');
            // Not `business_id`: that name would make it tenant data, and the
            // shared layers must stay visible to every business.
            $table->foreignId('proposed_by_business_id')->nullable()->constrained('businesses')->cascadeOnDelete()->comment('Null = compartida; con valor = propuesta que solo vio ese negocio');
            $table->string('key', 80)->unique()->comment('Clave estable que devuelve la IA (price, laboratorio.preparacion...); nunca se traduce');
            $table->string('name', 80)->comment('Cómo lo lee el dueño en las estadísticas');
            $table->string('description')->comment('Qué entra en esta intención: la guía que lee la IA para elegir');
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Same space as the questions: a proposal that means an existing
            // intent reuses it instead of forking the topic.
            $table->vector('embedding', dimensions: config('rag.embedding.dimensions'))->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_intents');
    }
};
