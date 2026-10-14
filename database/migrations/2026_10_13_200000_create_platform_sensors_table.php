<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the platform is watching, and what each thing says RIGHT NOW.
     *
     * The feed (`platform_events`) is what happened; this is what is true at
     * the last look, with the hour of that look. A reading with no hour is
     * a guess, so the board reads `checked_at` before it trusts the colour.
     */
    public function up(): void
    {
        Schema::create('platform_sensors', function (Blueprint $table): void {
            $table->id();
            $table->string('sensor_key', 40)->unique()->comment('queue, database, disk, receipts… one row per thing watched');
            $table->string('group', 20)->comment('system, customers, money, compliance, external');
            $table->string('status', 10)->comment('ok, warn, crit, unknown');
            $table->string('value', 60)->nullable()->comment('The figure the tile shows: "81 %", "8 / 9"');
            $table->string('detail', 160)->nullable()->comment('The line under the name');
            $table->unsignedSmallInteger('failures')->default(0)->comment('Consecutive looks that were not ok: a blip is not an outage');
            $table->timestamp('checked_at')->comment('The last time the sweep looked');
            $table->timestamp('changed_at')->comment('The last time the status changed');
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sensors');
    }
};
