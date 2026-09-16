<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The client's own shelves for the service list (Fresha pattern). They
     * map to WhatsApp catalog collections later, so the display order the
     * client drags into is data, not presentation.
     */
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();

            // The tenant. Its shelves leave with it, like its services do.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // The drag order of the shelves; the assistant offers in it too.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Deleting the user leaves the shelf: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One "Cortes" per business; every business may have its own.
            $table->unique(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
