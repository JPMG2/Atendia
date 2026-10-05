<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The models the platform may use, and what each one COST on a given day.
     *
     * Prices are versioned, not overwritten: the cost report read one global
     * rate, so the first model change would have revalued every past call.
     * The provider rides along, because what gets called is the pair.
     */
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();

            $table->string('provider', 20)->comment("La clave del proveedor en config('ai.providers'), ej. openai");
            $table->string('code', 80)->comment('El identificador del proveedor, ej. gpt-6-astra');
            $table->string('label', 60)->comment('Nombre legible para el panel');

            // Per MILLION tokens, which is how providers publish them.
            $table->decimal('prompt_per_million', 10, 4)->comment('USD por millón de tokens de entrada');
            $table->decimal('cached_per_million', 10, 4)->comment('USD por millón de tokens de entrada cacheados');
            $table->decimal('completion_per_million', 10, 4)->comment('USD por millón de tokens de salida');

            $table->date('effective_from')->comment('Desde cuándo rige este precio: lo anterior conserva el suyo');
            $table->string('source')->nullable()->comment('De dónde salió el precio, para poder auditarlo');
            $table->boolean('is_active')->default(true)->comment('Si puede elegirse para una tarea');

            $table->timestamps();

            $table->unique(['code', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
