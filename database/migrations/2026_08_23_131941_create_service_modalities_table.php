<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modalidades: CÓMO se ofrece un servicio, no qué se ofrece.
 *
 * Es la capa que hace que AtendIa sirva a cualquier oficio sin tocar código. Un
 * tipo de servicio hereda UNA modalidad, y la modalidad es la que decide qué le
 * pregunta el asistente y qué tiene que recordar el sistema: un turno necesita
 * agenda, una reserva necesita capacidad, un alquiler necesita devolución.
 *
 * `code` es la bisagra con el código: la lógica se engancha ahí, NO al id ni al
 * nombre (que el admin puede editar). Una modalidad con un `code` que el sistema
 * todavía no conoce degrada a comportamiento base en vez de romper — por eso
 * esto es una tabla y no un enum: nombrar, describir, ordenar y activar no
 * necesitan un programador.
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

            // Si se borra el usuario, el maestro queda: solo se pierde el autor.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            // Un maestro no se borra: los tipos de servicio quedarían colgando.
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_modalities');
    }
};
