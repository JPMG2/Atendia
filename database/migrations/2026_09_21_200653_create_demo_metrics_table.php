<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hero demo's funnel, one row per day: visitors who tried it, the
     * replies they consumed, and how many went on to register. Platform
     * metrics — no business_id on purpose; the future admin dashboard reads
     * them.
     */
    public function up(): void
    {
        Schema::create('demo_metrics', function (Blueprint $table): void {
            $table->id();

            $table->date('day')->unique();
            $table->unsignedInteger('sessions')->default(0)->comment('Visitantes que mandaron su primer mensaje a la demo');
            $table->unsignedInteger('messages')->default(0)->comment('Respuestas de la demo servidas (costo real en tokens)');
            $table->unsignedInteger('registrations')->default(0)->comment('Registros de visitantes que antes probaron la demo');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_metrics');
    }
};
