<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The compliance history of a business: what happened and what was done about it.
 *
 * Suspending and lifting only overwrote `suspended_at`, so a business suspended
 * twice kept no history. Events are written when they happen and never edited.
 * Dated before the RLS migration, which has to find the table there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30)->comment('ComplianceEventKind: content_rejected, suspended, payment_late…');
            $table->string('detail')->nullable()->comment('What it was: the moderation category, the reason, an excerpt of the appeal');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()->comment('Who decided it, when a person did');
            $table->timestamp('occurred_at')->comment('When it happened, which a backfilled row does not share with created_at');
            $table->timestamps();

            $table->index(['business_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_events');
    }
};
