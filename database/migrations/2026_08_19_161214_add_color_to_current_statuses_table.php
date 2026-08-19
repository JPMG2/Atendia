<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('current_statuses', function (Blueprint $table): void {
            // Guarda la CLAVE de un token semántico ('success', 'danger'…), nunca
            // un hex: un color fijo se ve igual en claro y en oscuro, y varios
            // quedan ilegibles en el tema oscuro. El token se adapta solo.
            // La paleta permitida vive en CurrentStatus::COLORS.
            $table->string('color', 20)->default('neutral')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('current_statuses', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }
};
