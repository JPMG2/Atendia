<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which model answers which task.
     *
     * The model was written by hand in all twelve agents, so trying a cheaper
     * one on a mechanical task — which `ia-economia-tokens.md` §8 already
     * asks for — meant editing code. Here it is a row, and the orchestrator
     * middleware applies it without the agent knowing.
     */
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $table): void {
            $table->id();

            $table->string('key', 80)->unique()->comment('El agente, ej. AskAtendia: lo que hace, no el modelo que usa');
            $table->string('label', 80)->comment('Qué hace esta tarea, en palabras');
            $table->string('model_code', 80)->nullable()->comment('Modelo asignado; null = el que trae el agente en su atributo');
            $table->string('fallback_model_code', 80)->nullable()->comment('Modelo de respaldo si el proveedor del primero falla; de OTRO proveedor, o no entra en la escalera');
            $table->boolean('is_mechanical')->default(false)->comment('Tarea de máquina: candidata a modelo barato, pero se decide MIDIENDO');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tasks');
    }
};
