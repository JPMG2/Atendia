<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devices a user has signed in from, keyed by a user-agent fingerprint.
     * Membership data like users itself: no business_id on purpose — the
     * alert must fire before any tenant context exists.
     */
    public function up(): void
    {
        Schema::create('login_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('ip', 45);
            $table->string('location')->nullable();
            $table->text('user_agent');
            $table->timestamp('last_login_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_devices');
    }
};
