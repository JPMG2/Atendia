<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The read mark is PER PERSON while the notice belongs to the business: a
     * shared mark would hide "Carla is waiting" from whoever opens the panel
     * second. No `business_id` here on purpose — a read is reachable only
     * through its notification, which the tenant scope already fences.
     */
    public function up(): void
    {
        Schema::create('panel_notification_reads', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('panel_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('read_at')->comment('Cuándo esta persona la leyó');

            $table->unique(['panel_notification_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_notification_reads');
    }
};
