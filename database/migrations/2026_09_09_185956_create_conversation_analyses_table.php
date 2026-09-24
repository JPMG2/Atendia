<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One AI reading of a finished stretch of a thread. A reopened thread
     * gets a second row for its new stretch only, so nothing is judged twice.
     */
    public function up(): void
    {
        Schema::create('conversation_analyses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('first_message_id')->comment('Primer mensaje del tramo analizado');
            $table->unsignedBigInteger('last_message_id')->comment('Último mensaje del tramo: desde acá sigue el próximo análisis');
            $table->string('sentiment', 8)->comment('positive | neutral | negative — cómo terminó el cliente');
            $table->unsignedInteger('prompt_tokens')->nullable()->comment('Costo real del análisis, igual que en los mensajes');
            $table->unsignedInteger('completion_tokens')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_analyses');
    }
};
