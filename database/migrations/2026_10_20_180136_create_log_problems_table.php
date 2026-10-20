<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-level on purpose: no business_id. A problem is what the logs
        // say about the whole platform, grouped so forty lines read as one.
        Schema::create('log_problems', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 40)->unique();
            $table->string('level', 16);
            $table->string('title', 300);
            $table->string('exception', 200)->nullable();
            $table->string('location', 300)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            // Where the newest occurrence sits, so the detail can read its trace from there.
            $table->string('last_file')->nullable();
            $table->string('state', 16)->default('active')->index();
            $table->timestamp('state_changed_at')->nullable();
            $table->foreignId('state_changed_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when a resolved problem shows up again; cleared when it is resolved again.
            $table->timestamp('regressed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_problems');
    }
};
