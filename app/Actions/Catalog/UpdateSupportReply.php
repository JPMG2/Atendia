<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SupportReply;

class UpdateSupportReply
{
    /**
     * @param  array{name: string, body: string, is_active: bool}  $data  Already validated by SupportReplyForm.
     */
    public function handle(int $id, array $data): SupportReply
    {
        $record = SupportReply::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
