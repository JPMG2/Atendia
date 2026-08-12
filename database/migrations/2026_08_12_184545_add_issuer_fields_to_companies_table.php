<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `companies` es AtendIa: el emisor de la factura, un ÚNICO registro.
 *
 * No confundir con `businesses`, que son los negocios que contratan el servicio
 * (los tenants). Acá viven los datos que encabezan una factura emitida por vos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('legal_name')->comment('Razón social del emisor');
            $table->string('tax_id', 20)->comment('CUIT');
            $table->foreignId('tax_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo_path')->nullable()->comment('Para el encabezado de la factura');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_condition_id');
            $table->dropColumn([
                'legal_name',
                'tax_id',
                'address',
                'email',
                'phone',
                'logo_path',
            ]);
        });
    }
};
