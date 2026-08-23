<?php

declare(strict_types=1);

use App\Models\Region;
use App\Models\TaxCondition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `companies` es AtendIa: los datos de MI compañía. Un ÚNICO registro, para siempre.
 *
 * No confundir con `businesses`, que son los negocios que contratan el servicio.
 * Acá vive lo que encabeza una factura emitida por AtendIa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_name')->comment('Razón social');
            $table->string('tax_id', 20)->comment('Número de identificación fiscal (RIF / CUIT)');
            $table->foreignIdFor(Region::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(TaxCondition::class)->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('web')->nullable();
            $table->string('logo_path')->nullable()->comment('Para el encabezado de la factura');
            $table->string('text_copyright')->nullable()->comment('Texto que va al pie de la factura');
            $table->string('tagline')->nullable()->comment('Texto que va al encabezado de la factura');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
