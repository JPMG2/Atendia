<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bell's inbox: one row per event worth coming back to, owned by the
     * business so whoever attends the panel sees it. `dedupe_key` is what keeps
     * a sweep that runs every few minutes from stacking twenty rows for the
     * same waiting customer: the raiser revives the row instead of adding one.
     */
    public function up(): void
    {
        Schema::create('panel_notifications', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30)->comment('El evento: customer_waiting | handed_to_team | appointment_booked | whatsapp_disconnected');
            $table->string('dedupe_key', 120)->comment('Identifica el HECHO (ej. waiting:42): el mismo hecho revive su fila, no suma otra');
            $table->json('payload')->comment('Lo que la línea necesita para escribirse (nombre, hora, minutos)');
            $table->string('url', 300)->nullable()->comment('La pantalla que resuelve el aviso; null = no lleva a ningún lado');

            $table->timestamps();

            // The feed reads by business and freshness; the dedupe key is
            // looked up on every raise, so it pays for its own index.
            $table->index(['business_id', 'created_at']);
            $table->unique(['business_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_notifications');
    }
};
