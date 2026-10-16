<?php

declare(strict_types=1);

namespace App\Traits;

use App\Interfaces\Catalog\Removable;
use Illuminate\Support\Facades\DB;

/**
 * The counting half of {@see Removable}.
 *
 * Counts go straight to the table with no soft-delete filter: a holder that
 * was removed can be restored, and the foreign key sees it either way.
 */
trait CountsUsage
{
    /** Nothing goes with it unless the master says so. */
    public function owns(): array
    {
        return [];
    }

    /** Foreign keys with CASCADE take their own configuration; a master with RESTRICT ones overrides this. */
    public function detachOwned(): void {}

    /** How many rows of `$table` point at this record through `$column`. */
    protected function heldBy(string $table, string $column, mixed $value = null): int
    {
        return DB::table($table)->where($column, $value ?? $this->getKey())->count();
    }

    /**
     * Keeps only what is actually holding the record, so the message never
     * says "0 negocios".
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    protected function onlyHeld(array $counts): array
    {
        return array_filter($counts, fn (int $count): bool => $count > 0);
    }
}
