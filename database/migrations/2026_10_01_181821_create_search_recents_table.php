<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each person opened last from the palette. In a table and not in the
 * session so the owner finds her own trail from the phone too; the row keeps
 * the hit as it was shown, because the thing it points at may be gone by then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_recents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hit_key', 120)->comment('group:id — the hit identity, so a repeat climbs instead of duplicating');
            $table->string('group_key', 80);
            $table->string('icon', 40);
            $table->string('title', 180);
            $table->string('subtitle', 180)->nullable();
            $table->string('url', 400);
            // Microseconds, not seconds: two picks inside the same second are
            // normal here, and updated_at is the only thing ordering the trail.
            $table->timestamps(6);

            $table->unique(['user_id', 'hit_key']);
            $table->index(['user_id', 'updated_at']);
        });

        // The RLS migration is older than this table, so it cannot fence it:
        // a tenant table born later carries its own policy, same wording.
        $policy = "NULLIF(current_setting('app.current_tenant', true), '') IS NULL"
            ." OR business_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint";

        DB::statement('ALTER TABLE search_recents ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE search_recents FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_isolation ON search_recents FOR ALL USING ({$policy}) WITH CHECK ({$policy})");
    }

    public function down(): void
    {
        Schema::dropIfExists('search_recents');
    }
};
