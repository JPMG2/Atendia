<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The days ONE professional does not attend: their holidays, a course, a
 * leave. The business keeps working; only their agenda is shut. Whole days
 * only — the business's own special hours are about the door, not about them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->comment('Same as starts_on for a single day');
            $table->string('reason', 80)->nullable();
            $table->timestamps();

            $table->index(['professional_id', 'starts_on', 'ends_on']);
        });

        // Born after the RLS migration, so it carries its own fence.
        $policy = "NULLIF(current_setting('app.current_tenant', true), '') IS NULL"
            ." OR business_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint";

        DB::statement('ALTER TABLE professional_closures ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE professional_closures FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_isolation ON professional_closures FOR ALL USING ({$policy}) WITH CHECK ({$policy})");
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_closures');
    }
};
