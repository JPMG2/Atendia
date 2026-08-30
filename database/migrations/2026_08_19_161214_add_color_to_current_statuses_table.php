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
            // Stores a semantic token KEY, never a hex: a fixed colour looks the
            // same in light and dark and several go unreadable there. The allowed
            // palette lives in CurrentStatus::COLORS.
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
