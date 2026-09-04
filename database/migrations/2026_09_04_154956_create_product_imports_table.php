<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per spreadsheet a business hands over: the stored file, the
 * confirmed column mapping and the processing state. The queued import job
 * reads from here, so a crash can always resume from the record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('original_name')->comment('El nombre del archivo tal cual lo subió el negocio');
            $table->string('path');

            // The confirmed mapping, as [{column, target}]: a list, not a map,
            // because two columns may carry the same header.
            $table->json('mapping');

            $table->unsignedInteger('total_rows')->default(0);

            $table->string('status')->default('pending')->comment('pending | processing | done | failed');

            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
