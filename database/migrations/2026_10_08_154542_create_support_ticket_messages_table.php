<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the team said on a report, and what it said to itself.
     *
     * One column was all a report could hold of our side, so it was answered
     * once and the form vanished. Here the conversation is a list, and an
     * internal note is a row that never leaves the team (`is_internal`).
     * `delivery` is how the reply really left, null for a note.
     */
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete()->comment('Denormalised so the tenant fence needs no join');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('The team member who wrote it');
            $table->text('body');
            $table->boolean('is_internal')->default(false)->comment('A note for the team: the business never reads it');
            $table->string('delivery', 12)->nullable()->comment('How the reply left: whatsapp, email, no_contact or failed');
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        // Born after the RLS migration, so it carries its own fence.
        $policy = "NULLIF(current_setting('app.current_tenant', true), '') IS NULL"
            ." OR business_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint";

        DB::statement('ALTER TABLE support_ticket_messages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE support_ticket_messages FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_isolation ON support_ticket_messages FOR ALL USING ({$policy}) WITH CHECK ({$policy})");
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
