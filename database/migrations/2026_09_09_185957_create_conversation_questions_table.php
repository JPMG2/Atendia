<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every real question a customer asked, rewritten so it stands on its
     * own ("¿y los sábados?" becomes "¿Abren los sábados?"). The semantic
     * unit topics, suggestions and statistics are built on.
     */
    public function up(): void
    {
        Schema::create('conversation_questions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->nullable()->constrained()->nullOnDelete()->comment('El mensaje del cliente donde se preguntó');
            $table->foreignId('question_intent_id')->nullable()->constrained()->nullOnDelete()->comment('Null = no encaja en ninguna intención conocida');

            $table->string('question', 500)->comment('La pregunta completa, sin depender de la charla, en español neutro');
            $table->string('subject', 120)->nullable()->comment('El objeto por el que pregunta ("Perfil tiroideo"); null si no hay uno');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete()->comment('El servicio del catálogo al que apunta el objeto');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete()->comment('El producto del catálogo; con objeto y sin ninguno de los dos = no está en el catálogo');
            $table->string('resolved_by', 10)->comment('assistant | team | nobody');
            $table->timestamp('asked_at')->comment('Cuándo lo preguntó el cliente; created_at es cuándo se analizó (horas o días después)');
            $table->text('answer')->nullable()->comment('Lo que respondió el equipo, reescrito general: el borrador de la sugerencia');
            $table->timestamp('customer_notified_at')->nullable()->comment('Cuándo se le mandó la respuesta enseñada al cliente que se quedó sin ella');
            $table->foreignId('knowledge_suggestion_id')->nullable()->constrained()->nullOnDelete()->comment('La sugerencia que junta esta pregunta con sus iguales');
            // Same space as the RAG chunks: the meaning vector of the
            // REWRITTEN question, never of the raw context-bound message.
            $table->vector('embedding', dimensions: config('rag.embedding.dimensions'))->nullable()->index();

            $table->timestamps();

            $table->index(['business_id', 'asked_at']);
            $table->index('conversation_id');
            $table->index('knowledge_suggestion_id');
            $table->index('question_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_questions');
    }
};
