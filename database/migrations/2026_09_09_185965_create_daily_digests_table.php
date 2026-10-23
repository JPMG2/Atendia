<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner's end-of-day read, kept. The closing mail used to be the only copy:
 * the tally it is built from is cleared the moment the mail leaves, so nothing
 * could show yesterday's read again. One row per business per local day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_digests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('for_day')->comment('The business-local day the read covers');
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('contacts')->default(0);
            $table->text('summary')->default('');
            $table->json('questions')->comment('What customers asked, most recent first, no repeats');
            $table->unsignedSmallInteger('referred')->default(0);
            $table->unsignedSmallInteger('appointments_tomorrow')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'for_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_digests');
    }
};
