<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The second belt of tenant isolation: Postgres itself fences every query —
 * raw SQL included — by the `app.current_tenant` setting that Tenant pushes.
 * FORCE matters: the app connects as the table OWNER, which RLS would
 * otherwise exempt. No tenant set means an open policy on purpose: that is
 * the admin, the console and the seeders, mirroring the Eloquent scope.
 */
return new class extends Migration
{
    /**
     * Every table owned by a business. A new tenant table joins this list
     * AND gets caught by the guardian if it forgets to.
     *
     * @var list<string>
     */
    private const array TABLES = [
        'services',
        'products',
        'product_imports',
        'business_hours',
        'knowledge_documents',
        'knowledge_chunks',
    ];

    /**
     * NULLIF folds the "never set" and "set to empty" cases into one before
     * casting, so the bigint cast can never blow up on ''.
     */
    private const string POLICY = "NULLIF(current_setting('app.current_tenant', true), '') IS NULL"
        ." OR business_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint";

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(sprintf(
                'CREATE POLICY tenant_isolation ON %s FOR ALL USING (%s) WITH CHECK (%s)',
                $table,
                self::POLICY,
                self::POLICY,
            ));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
