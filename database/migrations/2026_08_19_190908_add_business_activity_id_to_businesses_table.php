<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business declares ITS activity: one activity has many businesses, a
 * business has one.
 *
 * The sector is not stored alongside — the activity already knows its own, and
 * two columns would open the door to them contradicting each other. Nullable
 * because the businesses already on file have not picked one yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignIdFor(BusinessActivity::class)
                ->nullable()
                ->after('country_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_activity_id');
        });
    }
};
