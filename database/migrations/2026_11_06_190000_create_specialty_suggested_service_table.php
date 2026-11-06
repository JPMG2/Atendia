<?php

declare(strict_types=1);

use App\Models\Specialty;
use App\Models\SuggestedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which suggested services a specialty brings along.
 *
 * The suggestion itself stays a row of its activity; this only says "this one
 * is for a business that marked THAT specialty". A suggestion linked here is
 * left out of the activity's general list, so a neurologist's services do not
 * flood every medical office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialty_suggested_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Specialty::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(SuggestedService::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['specialty_id', 'suggested_service_id'], 'specialty_suggested_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialty_suggested_service');
    }
};
