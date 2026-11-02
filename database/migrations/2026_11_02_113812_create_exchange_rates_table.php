<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bolivar rate the platform charges with, one row per value date (the
     * BCV publishes the next business day's rate in advance). Platform data,
     * not tenant: every business pays at the same official rate.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();

            $table->string('currency', 3)->default('VES')->comment('Moneda local contra el dólar');
            $table->date('rate_date')->comment('Fecha valor que publica el BCV');
            $table->decimal('official', 14, 4)->comment('Bs por dólar con los que se cobra (oficial BCV)');
            $table->string('source', 20)->comment('bcv_page | dolarapi | manual');
            $table->decimal('parallel', 14, 4)->nullable()->comment('Paralelo: solo referencia, nunca cobra');
            // A jump bigger than the allowed one waits for her before it charges anybody.
            $table->timestamp('confirmed_at')->nullable()->comment('Null = salto fuera de rango esperando confirmación');
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete()->comment('Quién la cargó o confirmó a mano');
            $table->timestamps();

            $table->unique(['currency', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
