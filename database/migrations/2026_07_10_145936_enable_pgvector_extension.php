<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The "vector" extension is not trusted, so creating it requires a
     * superuser. The app connects as a non-superuser role, therefore the
     * extension is provisioned once per database by a superuser (already done
     * for "atendia" and "atendia_testing"). With the extension present this
     * statement is a privilege-free no-op, keeping the migration idempotent.
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
