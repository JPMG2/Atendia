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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            // A new address waits here until its owner proves the inbox is
            // theirs: the login email only switches once the link is clicked.
            $table->string('pending_email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Feeds the security checkup's "changed N months ago".
            $table->timestamp('password_changed_at')->nullable();
            // Null = login codes go by e-mail; a date = they go to the owner's WhatsApp.
            $table->timestamp('two_factor_whatsapp_at')->nullable();
            // Hashes only: the one-time backup codes are shown once and never stored in clear.
            $table->json('two_factor_recovery_codes')->nullable();
            $table->string('avatar_path')->nullable();
            $table->rememberToken();
            $table->timestamps();
            // Closing an account never erases it: the data stays for the
            // restore window and, after it, for the admin.
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
