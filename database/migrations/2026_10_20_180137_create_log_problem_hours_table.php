<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per problem per hour: enough for the trend and the periods
        // without keeping a row per log line.
        Schema::create('log_problem_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('log_problem_id')->constrained()->cascadeOnDelete();
            $table->timestamp('hour');
            $table->unsignedInteger('occurrences');

            $table->unique(['log_problem_id', 'hour']);
            $table->index('hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_problem_hours');
    }
};
