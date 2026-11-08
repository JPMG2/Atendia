<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The landing's sales chat, tallied per day: opened, quick answers read and
     * hand-offs to the sales WhatsApp. Counters only, by her decision: no
     * conversation is stored. Platform metrics, so no business_id on purpose.
     */
    public function up(): void
    {
        Schema::create('sales_chat_metrics', function (Blueprint $table): void {
            $table->id();

            $table->date('day')->unique();
            $table->unsignedInteger('opens')->default(0)->comment('Visitantes que abrieron el chat de ventas (una vez por sesión)');
            $table->unsignedInteger('answers')->default(0)->comment('Respuestas rápidas leídas');
            $table->unsignedInteger('handoffs')->default(0)->comment('Pasos al WhatsApp de ventas');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_chat_metrics');
    }
};
