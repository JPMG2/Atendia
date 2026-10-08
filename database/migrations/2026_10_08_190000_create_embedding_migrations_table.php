<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moving the knowledge search to another embedding model. Every vector in the
 * base was made by the old one, so the move is a process with steps, and each
 * attempt leaves a row saying where it stands and which model was left behind.
 * The model in force is the newest one that was switched on; with no row at
 * all it is the one written in config/rag.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embedding_migrations', function (Blueprint $table): void {
            $table->id();

            $table->string('model', 80)->comment('El modelo al que se pasa, ej. text-embedding-3-large');
            $table->string('provider', 20)->comment("El laboratorio del modelo: el 'driver' de config('ai.providers')");
            $table->unsignedSmallInteger('dimensions')->comment('Largo del vector nuevo');
            $table->string('from_model', 80)->comment('El modelo que regía al empezar, para poder volver');
            $table->unsignedSmallInteger('from_dimensions');

            $table->string('status', 20)->default('building')->comment('building, ready, switched, finished, cancelled o rolled_back');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable()->comment('Cuando todo quedó convertido');
            $table->timestamp('switched_at')->nullable()->comment('Cuando el modelo nuevo pasó a regir');
            $table->timestamp('closed_at')->nullable()->comment('Cuando se descartó el anterior, se canceló o se volvió atrás');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embedding_migrations');
    }
};
