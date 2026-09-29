<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booked slot. Times are stored UTC and painted in the business's own
     * timezone, like every other timestamp here. Dated BEFORE the RLS
     * migration so the tenant policy covers it.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete()
                ->comment('Quién viene: el turno SIEMPRE es de una persona del directorio');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete()
                ->comment('El servicio reservado; null = turno general, dura lo que diga el negocio');
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete()
                ->comment('La charla donde se pidió, para volver al hilo desde la agenda');

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->comment('starts_at + duración del servicio: cierra el hueco');
            $table->string('status', 12)->default('confirmed')->comment('confirmed | cancelled | done | no_show');
            $table->string('source', 12)->default('assistant')->comment('assistant | owner');
            $table->string('notes')->nullable()->comment('Lo que pidió el cliente al reservar');
            $table->timestamp('reminder_sent_at')->nullable()->comment('El recordatorio sale una sola vez');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'starts_at']);
            $table->index(['business_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
