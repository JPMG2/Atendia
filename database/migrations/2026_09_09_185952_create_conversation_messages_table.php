<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every turn of a thread, in and out. `business_id` repeats the parent's
     * tenant on purpose: RLS fences each table by its own column.
     */
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            $table->string('direction', 8)->comment('in = el cliente escribe, out = el asistente responde');
            $table->string('author', 9)->nullable()->comment('En las filas out: assistant | human (respondió el dueño desde el panel)');
            $table->string('kind', 8)->default('message')->comment('message | note — la nota interna jamás viaja al cliente');
            $table->string('wa_message_id', 100)->nullable()->comment('El id del mensaje en WhatsApp, para reacciones y trazas');
            $table->text('body');
            $table->boolean('is_enquiry')->default(false)->comment('Filas in: true si es una consulta real; un "sí", "ok" o "gracias" no cuenta para aprender ni para las estadísticas');
            $table->boolean('needs_teaching')->default(false)->comment('Filas in: la IA juzgó que el asistente no pudo responderla con lo que sabe — ahí se ofrece enseñarle');
            $table->string('topic', 80)->nullable()->comment('Filas in: el tema corto que la IA le asignó ("Horario de hoy"), lo que muestran las estadísticas');
            $table->unsignedInteger('prompt_tokens')->nullable()->comment('Tokens de entrada del intercambio (solo filas out; suma sus pasadas): el costo real que decide los paquetes');
            $table->unsignedInteger('completion_tokens')->nullable()->comment('Tokens de salida del intercambio (solo filas out)');
            $table->unsignedSmallInteger('audio_seconds')->nullable()->comment('Duración de la nota de voz transcripta (solo filas in de audio)');
            $table->jsonb('knowledge_sources')->nullable()->comment('Filas out del asistente: [{id, title}] de los documentos de conocimiento que respaldaron la respuesta');
            // Same space as the RAG chunks (model + dimensions from rag.php):
            // powers "who asked about X" search over customer questions.
            $table->vector('embedding', dimensions: config('rag.embedding.dimensions'))->nullable()->index();
            $table->timestamps();

            // The thread reads oldest-first; id already carries that order.
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
