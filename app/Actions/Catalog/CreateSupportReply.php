<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SupportReply;

class CreateSupportReply
{
    /**
     * @param  array{name: string, body: string, is_active: bool}  $data  Already validated by SupportReplyForm.
     */
    public function handle(array $data): SupportReply
    {
        return SupportReply::query()->create($data);
    }
}
