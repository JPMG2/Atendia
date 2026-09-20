<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One WhatsApp thread per customer per business: the assistant's memory
     * and, next, the panel's Conversations screen. Machine-written by the
     * reply worker — no author columns on purpose.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();

            // The tenant. A customer chatting with two businesses is two threads.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete()->comment('La ficha del cliente final; nullable porque los hilos son anteriores a la ficha');

            $table->string('contact_phone', 30)->comment('El WhatsApp del cliente final, solo dígitos');
            $table->string('contact_name')->nullable()->comment('El pushName que muestra WhatsApp; lo pisa cada mensaje');
            $table->string('language', 8)->nullable()->comment('Idioma detectado del cliente: la IA lo espeja y la derivación traduce de vuelta');
            $table->string('status', 12)->default('open')->comment('open | team | customer | resolved — en team la IA calla en ESTE hilo');
            $table->timestamp('last_message_at')->nullable()->comment('Para ordenar la bandeja; lo sella cada intercambio');
            $table->timestamps();

            $table->unique(['business_id', 'contact_phone']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
