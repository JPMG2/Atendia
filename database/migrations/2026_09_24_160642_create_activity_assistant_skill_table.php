<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which trade gets which of its own skills: a salon "turnos", a lab
     * "turnos" and "resultados". Data, so a new trade needs no code.
     */
    public function up(): void
    {
        Schema::create('activity_assistant_skill', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_skill_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['business_activity_id', 'assistant_skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_assistant_skill');
    }
};
