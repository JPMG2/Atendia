<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Interfaces\Catalog\Removable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes one record of a catalog master, but only when nothing holds it.
 *
 * Forced, not soft: the check below counts every holder, removed ones
 * included, so nothing can point at the row and a soft delete would only
 * leave its code and name taken by something nobody can see. The foreign
 * keys stay as the backstop if a holder is ever missed from `usage()`.
 */
class RemoveCatalogRecord
{
    public const string REMOVED = 'removed';

    public const string IN_USE = 'in_use';

    public const string MISSING = 'missing';

    /**
     * What a delete would do, without doing it: the confirmation reads this.
     *
     * @param  Model&Removable  $blank  An empty instance of the master's model.
     * @return array{status: string, record: ?Model, usage: array<string, int>, owns: array<string, int>}
     */
    public function inspect(Model&Removable $blank, int $id): array
    {
        $record = $blank->newQuery()->find($id);

        if ($record === null) {
            return ['status' => self::MISSING, 'record' => null, 'usage' => [], 'owns' => []];
        }

        /** @var Model&Removable $record */
        $usage = $record->usage();

        return [
            'status' => array_sum($usage) > 0 ? self::IN_USE : self::REMOVED,
            'record' => $record,
            'usage' => $usage,
            'owns' => $record->owns(),
        ];
    }

    /** @param  Model&Removable  $blank  An empty instance of the master's model. */
    public function handle(Model&Removable $blank, int $id): string
    {
        return DB::transaction(function () use ($blank, $id): string {
            // Locked so two admins cannot both pass the check and then both
            // delete, nor a holder be attached between the check and the delete.
            $record = $blank->newQuery()->lockForUpdate()->find($id);

            if ($record === null) {
                return self::MISSING;
            }

            /** @var Model&Removable $record */
            if (array_sum($record->usage()) > 0) {
                return self::IN_USE;
            }

            // Its own configuration goes first: some of it hangs off RESTRICT keys.
            $record->detachOwned();

            $record->forceDelete();

            return self::REMOVED;
        });
    }
}
