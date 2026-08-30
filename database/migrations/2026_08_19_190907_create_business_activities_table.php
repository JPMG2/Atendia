<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities: the concrete trade a business is in.
 *
 * The FINE level of a sector and the one that really talks to the AI: the
 * assistant's tone, what it asks for and the trade's seed knowledge all hang
 * off it. Hence a globally unique `code` — it is the key that profile is
 * looked up by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_activities', function (Blueprint $table): void {
            $table->id();
            // Restrictive on purpose: a sector with activities on it is not deleted
            // in silence. Same criterion as the other masters.
            $table->foreignIdFor(BusinessSector::class)->constrained()->restrictOnDelete();
            $table->string('code', 40)->unique()->comment('Clave estable del oficio: farmacia, panaderia…');
            $table->string('name')->comment('Farmacia, Panadería, Peluquería');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The name is unique WITHIN the sector, not globally: two sectors may
            // each offer an activity by the same name.
            $table->unique(['business_sector_id', 'name']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_activities');
    }
};
