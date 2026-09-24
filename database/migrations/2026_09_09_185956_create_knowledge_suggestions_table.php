<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Tu asistente no supo esto": one row per DISTINCT question the
     * assistant could not answer. Every asking links here, so the count and
     * the team's answers come from the questions, never from a copy.
     */
    public function up(): void
    {
        Schema::create('knowledge_suggestions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_intent_id')->nullable()->constrained()->nullOnDelete()->comment('El tema por el que se agrupa en la lista');
            $table->foreignId('knowledge_document_id')->nullable()->constrained()->nullOnDelete()->comment('La respuesta enseñada, cuando el dueño la aprobó');

            $table->string('question', 500)->comment('La pregunta completa, tal como la reescribió el análisis la primera vez');
            $table->string('status', 10)->default('pending')->comment('pending | taught | dismissed');
            $table->vector('embedding', dimensions: config('rag.embedding.dimensions'))->nullable()->index();
            $table->timestamp('status_changed_at')->nullable()->comment('Cuándo se enseñó, descartó o reabrió');

            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_suggestions');
    }
};
