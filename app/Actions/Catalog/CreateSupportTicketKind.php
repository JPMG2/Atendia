<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SupportTicketKind;

class CreateSupportTicketKind
{
    /**
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
    public function handle(array $data): SupportTicketKind
    {
        return SupportTicketKind::query()->create($data);
    }
}
