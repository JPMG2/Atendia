<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One photo of the revenue per month, because today it only exists while
     * the screen is open: a business that leaves disappears from the sum as
     * if it had never paid, and "did I grow since September" has no answer.
     *
     * Not tenant: this is the platform's own accounting, not a business's.
     */
    public function up(): void
    {
        Schema::create('revenue_snapshots', function (Blueprint $table): void {
            $table->id();

            $table->date('month')->unique()->comment('Primer día del mes retratado: una foto por mes, y re-tomarla la corrige');

            $table->decimal('mrr', 12, 2)->comment('Ingreso mensual recurrente de ese mes, el anual prorrateado a 12');
            $table->unsignedInteger('paying')->comment('Suscripciones que lo sostienen');
            $table->unsignedInteger('trialing')->comment('En prueba: ingreso futuro, no actual');

            // The movements, which are what explains the total. A month that
            // gained 200 and lost 180 reads as "+20" and hides that half the
            // book changed hands.
            $table->decimal('gained', 12, 2)->default(0)->comment('Lo que entró: altas y subidas de plan contra el mes anterior');
            $table->decimal('lost', 12, 2)->default(0)->comment('Lo que se fue: bajas y caídas de plan contra el mes anterior');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_snapshots');
    }
};
