<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opening hours, one row per shift: LatAm businesses routinely split the day
 * (morning/afternoon), so a day can hold several rows. Structured rows — not
 * JSON — because "is it open NOW" and the sending window are SQL questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->comment('0 = domingo … 6 = sábado, como date("w")');
            $table->time('opens_at')->comment('Hora local del negocio (su timezone)');
            $table->time('closes_at')->comment('Hora local del negocio (su timezone)');
            $table->timestamps();

            $table->index(['business_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
