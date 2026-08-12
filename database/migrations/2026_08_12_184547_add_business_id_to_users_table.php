<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A qué negocio pertenece el usuario.
 *
 * NULLABLE a propósito: el admin (el dueño de AtendIa) no pertenece a ningún
 * negocio. "Sin business_id" es exactamente lo que distingue al admin de un
 * cliente, y es el caso que el scope de aislamiento tiene que contemplar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('business_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
