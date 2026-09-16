<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

            // The catalog mould (a "producto"-modality type). Nullable: a
            // hand-typed product waits untyped, same boundary as services.
            $table->foreignId('service_type_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('name')->comment('El nombre que le pone el negocio: Pan de campo, Alternador Palio 1.4');

            // The owner's own reference; 100 chars is Meta's retailer_id cap,
            // so it can double as the WhatsApp catalog sync key one day.
            $table->string('code', 100)->nullable();

            // Text, not varchar: Meta's catalog allows 9,999 chars and the
            // assistant answers better with the whole story.
            $table->text('description')->nullable();

            // Nullable ON PURPOSE: the client is never forced to publish a
            // price or keep stock counts just to have the product exist.
            $table->decimal('price', 12, 2)->nullable();

            // Decimal, not integer: a bakery counts units, a deli sells kilos.
            $table->decimal('stock', 12, 2)->nullable()->comment('Cantidad disponible, en la unidad del negocio');

            // Values of the type's attribute set, keyed by service_attribute_id
            // (never by code: renaming a code must not orphan values).
            $table->jsonb('attribute_values')->nullable();

            // Meta separates the two: out of stock stays VISIBLE ("sin stock
            // por ahora"); is_active below is stop selling it altogether.
            $table->boolean('in_stock')->default(true);

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

        // Partial unique: a filled code is a per-business identity (future
        // retailer_id), trashed rows included so a sync key never revives
        // duplicated; codeless products stay free of it.
        DB::statement('CREATE UNIQUE INDEX products_business_code_unique ON products (business_id, code) WHERE code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
