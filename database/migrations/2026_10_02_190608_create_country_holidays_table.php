<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A country's national holidays, so a business does not type its calendar one
 * day at a time. NOT tenant data: the same 1 de mayo serves every business of
 * that country. Two shapes in one table — a fixed date, or one derived from
 * Easter. The ones that move by decree are absent on purpose: a date nobody
 * can compute would be wrong on the screen of whoever trusted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedTinyInteger('month')->nullable()->comment('With `day`, a fixed date. Null when it hangs off Easter');
            $table->unsignedTinyInteger('day')->nullable();
            $table->smallInteger('easter_offset')->nullable()->comment('Days from Easter Sunday: -2 is Good Friday');
            $table->timestamps();

            $table->index(['country_id', 'month', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_holidays');
    }
};
