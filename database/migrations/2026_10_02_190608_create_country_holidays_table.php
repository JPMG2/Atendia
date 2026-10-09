<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A country's national holidays, so a business does not type its calendar one
 * day at a time. NOT tenant data: one row serves every business of that country.
 * Three shapes: a fixed date every year, a day counted from Easter, or the exact
 * date of ONE year (a decree, a bridge): only the first two can be computed.
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
            $table->date('on_date')->nullable()->comment('A holiday of ONE year: that exact date, and it does not repeat');
            $table->boolean('is_active')->default(true)->comment('Off = ignored everywhere, without deleting the row');
            $table->timestamps();

            $table->index(['country_id', 'month', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_holidays');
    }
};
