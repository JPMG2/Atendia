<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform's own feed: what the admin has to know, with its state.
     *
     * It belongs to the PLATFORM, not to a tenant, so the business it is about
     * is `about_business_id` and there is no row-level security on it: only the
     * admin panel ever reads it. A fact that repeats is ONE row that counts
     * (the dedupe key), the way an on-call tool groups an alert.
     */
    public function up(): void
    {
        Schema::create('platform_events', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 40)->comment('PlatformEventKind: business_joined, receipt_received, business_suspended, whatsapp_disconnected…');
            $table->string('severity', 12)->comment('critical interrupts, important waits on the board, info only informs');
            $table->string('state', 14)->default('open')->comment('open → acknowledged → resolved; a health condition resolves itself');
            $table->foreignId('about_business_id')->nullable()->constrained('businesses')->nullOnDelete()->comment('The business it is about; null for the platform itself');
            $table->string('dedupe_key', 120)->nullable()->comment('One open row per key: a repeat counts, it does not add a line');
            $table->string('detail')->nullable()->comment('What it was, short; free text is somebody\'s own and is shown as written');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('notified_at')->nullable()->comment('When it was pushed to her phone, if it was');
            $table->timestamp('emailed_at')->nullable()->comment('When it was e-mailed to the admin, if it was: a critical must not ride only on the WhatsApp line that may be down');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'severity', 'last_seen_at']);
            $table->index('about_business_id');
        });

        // Postgres only: a resolved row may repeat its key, an open one may not.
        DB::statement("CREATE UNIQUE INDEX platform_events_open_dedupe ON platform_events (dedupe_key) WHERE dedupe_key IS NOT NULL AND state <> 'resolved'");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_events');
    }
};
