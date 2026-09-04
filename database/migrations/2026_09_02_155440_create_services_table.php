<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business's OWN services: what each tenant offers, in its own words.
 *
 * NOT a catalog master — those are global and the admin's. Each row hangs off
 * a business and points at the service TYPE (the catalog mould) that lends it
 * behavior. Only the name is mandatory: letting the assistant answer
 * "do you offer X?" is the floor; everything else is optional detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();

            // The tenant. Its services leave with it, like its knowledge does.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The mould lending behavior. Nullable: a hand-typed service the
            // catalog does not know waits untyped for a later classification.
            $table->foreignId('service_type_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('name')->comment('El nombre que le pone el negocio: Ecodoppler, Dobladillo, Torta de bodas');
            $table->string('description')->nullable();

            // Nullable ON PURPOSE: the client is never forced to publish a
            // price or a length just to have the service exist.
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->boolean('is_active')->default(true)->comment('Dejar de ofrecerlo sin borrarlo');

            // Deleting the user leaves the service: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One "Ecodoppler" per business; every business may have its own.
            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
