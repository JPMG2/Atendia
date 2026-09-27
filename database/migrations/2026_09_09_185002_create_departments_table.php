<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name', 60)->comment('Ventas, Pagos, Turnos… lo nombra la dueña');
            $table->text('routing_hint')->comment('"Deriva aquí cuando…": lo lee la IA para elegir el departamento');
            $table->jsonb('hours')->nullable()->comment('Horario propio {día 0-6: [abre, cierra]}; null = el del negocio');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
        });

        Schema::create('department_user', function (Blueprint $table): void {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['department_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
    }
};
