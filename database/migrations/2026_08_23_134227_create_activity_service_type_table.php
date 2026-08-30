<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which service types are SUGGESTED to each activity.
 *
 * Suggestion and not permission — the decision the whole product rests on. So
 * there is NO constraint stopping anyone adopting an unsuggested type: a
 * missing row means "not shown first". It hangs off the ACTIVITY, since two
 * trades in one sector do not offer the same things.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_service_type', function (Blueprint $table): void {
            $table->id();

            // With the activity gone, its suggestions mean nothing.
            $table->foreignIdFor(BusinessActivity::class)->constrained()->cascadeOnDelete();

            // The type is shared across activities, so it is never hard-deleted.
            $table->foreignIdFor(ServiceType::class)->constrained()->restrictOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0)
                ->comment('En qué orden se le sugieren al negocio de esta actividad');

            $table->timestamps();

            $table->unique(['business_activity_id', 'service_type_id'], 'activity_service_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_service_type');
    }
};
