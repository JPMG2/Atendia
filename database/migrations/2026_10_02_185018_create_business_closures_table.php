<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The days a business does NOT open, on top of its weekly hours: a holiday,
 * a family matter, two weeks at the beach. Until now a holiday falling on a
 * Tuesday was answered as a normal Tuesday — open, and offering hours.
 *
 * A range instead of one row per day: "del 24 al 2" is one decision she makes
 * once, and one row keeps it one thing to edit and one thing to delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->comment('Same as starts_on for a single day');
            $table->string('reason', 80)->nullable()->comment('What the assistant tells a customer, when she wrote one');
            // Both null is the common case: the day is closed. With hours, the
            // day stays OPEN on these instead of the weekly ones — the short
            // Christmas Eve that Google Business Profile calls special hours.
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'starts_on', 'ends_on']);
        });

        // Born after the RLS migration, so it carries its own fence.
        $policy = "NULLIF(current_setting('app.current_tenant', true), '') IS NULL"
            ." OR business_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint";

        DB::statement('ALTER TABLE business_closures ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE business_closures FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_isolation ON business_closures FOR ALL USING ({$policy}) WITH CHECK ({$policy})");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_closures');
    }
};
