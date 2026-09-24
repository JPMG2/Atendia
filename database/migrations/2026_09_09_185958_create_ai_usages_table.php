<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The meter: one row per AI call of ANY kind, written by a single SDK
     * listener so no new agent can slip past it. The cost report and the
     * per-client usage read only from here.
     */
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete()->comment('Null = llamada de la plataforma sin negocio (consola, admin)');

            $table->string('kind', 40)->comment('Qué llamó: el agente (AsistenteAtendia, ConversationAnalyst...), embeddings o transcription');
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('input_tokens')->default(0)->comment('Entrada a tarifa completa (incluye la escritura en caché)');
            $table->unsignedInteger('cached_tokens')->default(0)->comment('Entrada leída de la caché del proveedor: se cobra más barata');
            $table->unsignedInteger('output_tokens')->default(0)->comment('Salida, razonamiento incluido');

            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
