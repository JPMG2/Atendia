<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes price, stock and duration out of the attribute library.
 *
 * They are FIRST-CLASS fields of what a business adopts: queried, sorted,
 * filtered and bulk-updated constantly, and they need currency, tax and
 * history. Buried in a generic jsonb, "raise the menu 10%" cannot be answered.
 * Really deleted: they are seed rows nobody references yet.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $codes = ['precio', 'stock', 'duracion'];

    public function up(): void
    {
        DB::table('service_attributes')->whereIn('code', $this->codes)->delete();
    }

    /**
     * Irreversible on purpose: recreating them means running an older seeder, not
     * undoing a DELETE with nothing to restore.
     */
    public function down(): void {}
};
