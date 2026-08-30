<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modalities: HOW a service is offered, not what.
 *
 * The layer that lets AtendIa serve any trade without touching code. A service
 * type inherits ONE, and the modality decides what the assistant asks. `code`
 * is the hinge the logic hooks onto, not the id or the editable name; an
 * unknown one degrades instead of breaking, which is why this is a table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_modalities', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique()->comment('Bisagra con el código: cita, reserva, alquiler…');
            $table->string('name')->unique()->comment('Cita / Turno, Reserva, Alquiler');
            $table->string('description')->nullable()->comment('Qué pide y qué recuerda, en una línea');
            $table->string('icon', 40)->nullable()->comment('Clave de config/icons.php');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the master row: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            // A master row is never deleted: the service types would dangle.
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_modalities');
    }
};
