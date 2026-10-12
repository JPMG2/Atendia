<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SupportTicketKind;

class UpdateSupportTicketKind
{
    /**
     * The `code` is the key every ticket of this kind holds, so it is set
     * once and never rewritten here: renaming is the `name`'s job.
     *
     * @param  array{
     *     code: string,
     *     name: string,
     *     icon: string,
     *     starting_priority: string,
     *     runs_clock: bool,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by SupportTicketKindForm::transformServiceData().
     */
    public function handle(int $id, array $data): SupportTicketKind
    {
        unset($data['code']);

        $kind = SupportTicketKind::query()->findOrFail($id);
        $kind->update($data);

        return $kind;
    }
}
