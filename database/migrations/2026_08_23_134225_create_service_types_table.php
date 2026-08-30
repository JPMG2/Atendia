<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use App\Models\ServiceModality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service types: WHAT a business offers.
 *
 * A type is GLOBAL, not tied to a sector, and who it is SUGGESTED to is the
 * pivot's call — a suggestion is never a ban. It inherits ONE modality; a type
 * wanting two is split in two. `business_sector_id` is ONLY grouping for the
 * admin screen, and nullable since a cross-cutting type has no sector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique()->comment('Clave estable que referencian el asistente y el RAG');
            $table->string('name')->unique()->comment('Consulta, Plato, Mesa, Arreglo');
            $table->string('description')->nullable();

            // Restrict: a modality with types hanging off it is taken out of
            // circulation by deactivating it, not by deleting it.
            $table->foreignIdFor(ServiceModality::class)->constrained()->restrictOnDelete();

            // SCREEN grouping, not permission. See the note above.
            $table->foreignIdFor(BusinessSector::class)->nullable()->constrained()->nullOnDelete()
                ->comment('Solo agrupa la pantalla del admin; la oferta la decide activity_service_type');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
