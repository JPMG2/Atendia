<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people who attend the bookings of a business: a doctor, a stylist, a
     * manicurist. Not a login and not a team seat — `user_id` only links one
     * when the professional also uses the panel. Dated BEFORE `appointments`
     * (which points at it) and before the RLS migration, so the tenant policy
     * covers it.
     */
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Only when the professional also signs in; most never do');

            $table->string('name', 80);
            $table->boolean('is_active')->default(true)->comment('Off = not offered for new bookings, past ones stay');
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Null on both = inherit the business value (appointment_capacity / appointments_per_day).
            $table->unsignedSmallInteger('appointment_capacity')->nullable()->comment('Bookings at the same hour; null = the business one');
            $table->unsignedSmallInteger('appointments_per_day')->nullable()->comment('Daily ceiling; null = the business one');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'is_active', 'sort_order']);
        });

        // The services a professional attends, with what changes for them. Every
        // override is optional: empty inherits the service's own value.
        Schema::create('professional_service', function (Blueprint $table): void {
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('duration_minutes')->nullable()->comment('Their own length for this service');
            $table->string('price_type')->nullable()->comment('fixed | from | free | talk, like services.price_type');
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('deposit', 12, 2)->nullable();

            $table->primary(['professional_id', 'service_id']);
        });

        // Their own weekly shifts, apart from business_hours: that table also decides
        // when customers may be written to and the "open now" clock.
        Schema::create('professional_hours', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week')->comment('0 = Sunday … 6 = Saturday, like business_hours');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index(['professional_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_hours');
        Schema::dropIfExists('professional_service');
        Schema::dropIfExists('professionals');
    }
};
