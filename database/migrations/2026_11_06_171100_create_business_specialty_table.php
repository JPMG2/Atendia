<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Specialty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The specialties a business marked, as many as it wants.
 *
 * Same shape as `activity_business` and the same isolation: it has no model of its
 * own and is only ever reached through `$business->specialties()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_specialty', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Business::class)->constrained()->cascadeOnDelete();
            // Restrictive: a specialty a business marked is retired by switching it off, never removed.
            $table->foreignIdFor(Specialty::class)->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'specialty_id'], 'business_specialty_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_specialty');
    }
};
