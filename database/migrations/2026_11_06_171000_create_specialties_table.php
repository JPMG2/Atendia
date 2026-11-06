<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialties: what a business marks inside its activity.
 *
 * A medical office marks the specialties it sees, a restaurant its cuisines. Each
 * one hangs off the activity that offers it; an activity that offers none carries
 * no field at all. It never changes the suggested services: those depend on the
 * activity alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table): void {
            $table->id();
            // Restrictive on purpose: an activity with specialties is not deleted in silence.
            $table->foreignIdFor(BusinessActivity::class)->constrained()->restrictOnDelete();
            $table->string('code', 60)->unique()->comment('Clave estable: medicina-interna, cocina-china…');
            $table->string('name')->comment('Medicina interna, Cocina china');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the master row: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            // A master row is never deleted: the businesses that marked it would dangle.
            $table->softDeletes();

            // The name is unique WITHIN the activity: two trades may each offer one by the same name.
            $table->unique(['business_activity_id', 'name']);
            $table->index(['business_activity_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
