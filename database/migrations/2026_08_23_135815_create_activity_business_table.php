<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The activities a business declares: ONE primary plus whatever it adds.
 *
 * This unlocks the case the whole design was arranged around: a bakery that
 * puts out tables adds a second activity and starts being suggested the room's
 * types. The primary one drives the assistant's tone and the reports, and a
 * PARTIAL unique index guarantees a single one per business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_business', function (Blueprint $table): void {
            $table->id();

            // With the business gone, its declarations mean nothing.
            $table->foreignIdFor(Business::class)->constrained()->cascadeOnDelete();

            // The activity is shared across businesses, so it is never hard-deleted.
            $table->foreignIdFor(BusinessActivity::class)->constrained()->restrictOnDelete();

            $table->boolean('is_primary')->default(false)
                ->comment('La que manda para el tono del asistente y el conocimiento del oficio');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['business_id', 'business_activity_id'], 'activity_business_unique');
        });

        // One primary per business. A PARTIAL Postgres index: it only covers the
        // primary rows, so there can be many secondary ones.
        DB::statement('CREATE UNIQUE INDEX activity_business_one_primary ON activity_business (business_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_business');
    }
};
