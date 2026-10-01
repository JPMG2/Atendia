<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a business reports to us. The `context` column is the whole reason this
 * is useful: the screen, the URL and the browser are captured instead of
 * asked, which is what lets the form demand one single field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 12)->unique()->comment('What the person is told to quote back');
            $table->string('kind', 20)->default('problem');
            $table->string('status', 20)->default('new');
            $table->string('screen', 80)->nullable()->comment('Route name it was opened from, null when opened with no screen in mind');
            $table->text('body');
            $table->string('attachment_path')->nullable()->comment('Only what the person chose to attach: nothing is captured on its own');
            $table->jsonb('context')->nullable()->comment('URL, viewport, browser, plan — captured, never asked');
            $table->text('reply')->nullable()->comment('What we answered, kept so the next person reads the same thing she did');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });

        // Born after the RLS migration, so it carries its own fence.
        $policy = "NULLIF(current_setting('app.current_tenant', true), '') IS NULL"
            ." OR business_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint";

        DB::statement('ALTER TABLE support_tickets ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE support_tickets FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_isolation ON support_tickets FOR ALL USING ({$policy}) WITH CHECK ({$policy})");
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
