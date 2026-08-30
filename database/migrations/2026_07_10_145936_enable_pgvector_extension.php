<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "vector" extension is not trusted, so creating it needs a superuser and
     * the app connects as a plain role. It is provisioned once per database by a
     * superuser; with it present this statement is a privilege-free no-op, which
     * keeps the migration idempotent.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
