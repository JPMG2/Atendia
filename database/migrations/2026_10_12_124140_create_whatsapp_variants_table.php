<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The number and the hours of the landing's WhatsApp inside a season.
 *
 * The evergreen lives on `companies` (phone, opening_schedule); a row here only
 * OVERRIDES it for the days of its window. What it leaves null is inherited.
 * The notice for the visitor is on the season itself, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seasonal_window_id')->constrained()->cascadeOnDelete()
                ->comment('The season this row speaks in; one row per season');
            $table->string('phone', 30)->nullable()->comment('Another number for these days; null = the company one');
            $table->jsonb('opening_schedule')->nullable()->comment('Week for these days; null = the company one, [] = closed all week');
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the row: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // Two live rows for one season resolve by coin flip; a soft-deleted one
        // must not block writing its replacement, hence the partial index.
        DB::statement('CREATE UNIQUE INDEX whatsapp_variants_season_unique ON whatsapp_variants (seasonal_window_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_variants');
    }
};
