<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the business's activity into the pivot and drops the column.
 *
 * The one on `businesses` becomes the PRIMARY. Keeping the column for "the
 * main one" and the pivot for "the rest" would be two sources of truth, and
 * they contradict each other sooner or later. Done now, before there are
 * businesses on file: the expensive migration at its cheapest moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill first: dropping the column ahead of it would lose the data.
        $rows = DB::table('businesses')
            ->whereNotNull('business_activity_id')
            ->get(['id', 'business_activity_id']);

        foreach ($rows as $row) {
            DB::table('activity_business')->insertOrIgnore([
                'business_id' => $row->id,
                'business_activity_id' => $row->business_activity_id,
                'is_primary' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignIdFor(BusinessActivity::class)
                ->nullable()
                ->after('country_id')
                ->constrained()
                ->restrictOnDelete();
        });

        // Only the primary comes back: the column cannot hold the secondary ones.
        $primaries = DB::table('activity_business')->where('is_primary', true)->get();

        foreach ($primaries as $primary) {
            DB::table('businesses')
                ->where('id', $primary->business_id)
                ->update(['business_activity_id' => $primary->business_activity_id]);
        }
    }
};
