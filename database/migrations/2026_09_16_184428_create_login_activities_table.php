<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per sign-in; created_at IS the login moment. Membership data
     * like login_devices: no business_id on purpose — written before any
     * tenant context exists. The listener prunes rows older than 90 days.
     */
    public function up(): void
    {
        Schema::create('login_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->string('location')->nullable();
            $table->text('user_agent');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};
