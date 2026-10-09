<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was done about an account while it sat on a step of the adoption ladder:
 * she wrote to it, or the team was told it stalled. Tied to the STEP, so moving
 * on makes the earlier mark stop counting without anything to clean up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adoption_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('step', 32)->comment('registered, business, catalog, whatsapp, conversation');
            $table->string('kind', 16)->comment('written, alerted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'step', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_marks');
    }
};
