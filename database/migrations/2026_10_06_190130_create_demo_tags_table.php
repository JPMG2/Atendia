<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rubro pills of the hero demo, which used to be hardcoded in `lang`.
 *
 * A seasonal variant is another row here with the same `slug` and a window:
 * what it fills overrides the evergreen, what it leaves null is inherited —
 * hence everything but the slug being nullable. When the window closes the
 * evergreen comes back whole, with nobody editing anything.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demo_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->comment('ferreteria, kiosco — the hinge to the demo business');
            $table->foreignId('seasonal_window_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('Null = the evergreen row; set = what replaces it inside that window');
            $table->string('label')->nullable()->comment('Text on the pill');
            $table->string('business_name')->nullable()->comment('Demo business the phone switches to');
            $table->string('noun', 60)->nullable()->comment('Lowercase rubro for the CTA copy');
            $table->jsonb('chips')->nullable()->comment('Suggested questions under the phone');
            $table->jsonb('pool')->nullable()->comment('Scripted conversation the carousel replays');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the row: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['slug', 'seasonal_window_id']);
        });

        // Postgres counts NULLs as distinct, so the unique above would happily
        // take two evergreens for one rubro — the one case nothing can resolve.
        DB::statement('CREATE UNIQUE INDEX demo_tags_evergreen_unique ON demo_tags (slug) WHERE seasonal_window_id IS NULL AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_tags');
    }
};
