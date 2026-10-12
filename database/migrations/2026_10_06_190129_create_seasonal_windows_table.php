<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WHEN of every seasonal change, in one place.
 *
 * A window is a date range that something else hangs off: the hero demo tags
 * and the landing's WhatsApp hours. The one thing it carries itself is the
 * `notice`, a line for the visitor: a second screen for it was the complaint.
 * Both ends are stored because the rollback is the window expiring.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seasonal_windows', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique()->comment('Navidad 2026, Vacaciones de invierno');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedSmallInteger('priority')->default(0)->comment('Highest wins when two windows overlap');
            $table->boolean('is_active')->default(true);
            $table->string('notice', 200)->nullable()->comment('Aviso que ve el visitante en la landing mientras corre: "Atendemos el 24 hasta las 14 h"');

            // Deleting the user leaves the window: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasonal_windows');
    }
};
