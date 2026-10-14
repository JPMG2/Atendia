<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every look at an infrastructure sensor, kept one day. `platform_sensors`
     * says what is true now; this says how it got there, which is what a
     * technician asks first ("was the disk climbing, or did it jump?").
     */
    public function up(): void
    {
        Schema::create('platform_sensor_readings', function (Blueprint $table): void {
            $table->id();
            $table->string('sensor_key', 40);
            $table->string('status', 10)->comment('ok, warn, crit, unknown');
            $table->timestamp('checked_at');

            $table->index(['sensor_key', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sensor_readings');
    }
};
