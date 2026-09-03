<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `suggested_services` are the CONCRETE services the catalog proposes per
 * activity, Google Business Profile style: "Corte de caballero", "Menú del
 * día". A suggestion, never a fence — the business adopts, edits or ignores
 * them. Each one carries the TYPE it hangs from, so an adopted suggestion is
 * born with its mould.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggested_services', function (Blueprint $table): void {
            $table->id();

            // The trade offering it: suggestions belong to the ACTIVITY, the
            // level that talks to the assistant.
            $table->foreignId('business_activity_id')->constrained()->cascadeOnDelete();

            // The mould lending behavior. Restrict: a type in use cannot go.
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();

            $table->string('name')->comment('El servicio concreto sugerido: Corte de caballero, Menú del día');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Orden por demanda dentro de la actividad');
            $table->boolean('is_active')->default(true)->comment('Dejar de sugerirlo sin borrarlo');

            // Deleting the user leaves the suggestion: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_activity_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggested_services');
    }
};
