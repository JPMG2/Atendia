<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The goals that earn a business a mention on the platform's own networks: the
 * number of conversations answered in a month, and the words of the post. The
 * metric stays in code (the screen branches on it); the thresholds and the
 * caption are data she can rewrite, add to or switch off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mention_goals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('threshold')->unique()->comment('Conversaciones atendidas en un mes que alcanzan la meta');
            $table->text('caption')->comment('El texto del post; {negocio} y {logro} se reemplazan al aceptar');
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the goal: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mention_goals');
    }
};
