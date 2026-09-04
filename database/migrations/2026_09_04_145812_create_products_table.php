<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business's OWN products: the goods each tenant sells, in its own words.
 *
 * The universal core the import maps onto — every trade shares it, none is
 * forced beyond it: only the name is mandatory. Columns the core does not
 * know (a lab study's preparation, a part's voltage) never land here: they
 * travel whole into the product's knowledge, where the assistant reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();

            // The tenant. Its products leave with it, like its services do.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name')->comment('El nombre que le pone el negocio: Pan de campo, Alternador Palio 1.4');
            $table->string('description')->nullable();

            // Nullable ON PURPOSE: the client is never forced to publish a
            // price or keep stock counts just to have the product exist.
            $table->decimal('price', 12, 2)->nullable();

            // Decimal, not integer: a bakery counts units, a deli sells kilos.
            $table->decimal('stock', 12, 2)->nullable()->comment('Cantidad disponible, en la unidad del negocio');

            $table->boolean('is_active')->default(true)->comment('Dejar de venderlo sin borrarlo');

            // Deleting the user leaves the product: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One "Pan de campo" per business; every business may have its own.
            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
