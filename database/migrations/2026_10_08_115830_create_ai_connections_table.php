<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The named doors to a lab: one per key, not one per lab.
     *
     * The platform has two OpenAI keys so customer-facing work and the
     * background tasks do not share a bill or a rate limit. The secret stays
     * in `.env`; this row only gives the entry of `config('ai.providers')` a
     * name the admin can read and a switch to take it out of use.
     */
    public function up(): void
    {
        Schema::create('ai_connections', function (Blueprint $table): void {
            $table->id();

            $table->string('key', 40)->unique()->comment("La entrada de config('ai.providers'), ej. openai-app");
            $table->string('label', 60)->comment('Cómo se llama en el panel, ej. OpenAI · clientes');
            $table->boolean('is_active')->default(true)->comment('Si puede elegirse para una tarea');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connections');
    }
};
