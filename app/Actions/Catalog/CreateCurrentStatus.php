<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CurrentStatus;

class CreateCurrentStatus
{
    /**
     * @param  array{
     *     name: string
     * }  $data  Already validated by CurrentStatusForm::transformServiceData().
     */
    public function handle(array $data): CurrentStatus
    {
        return CurrentStatus::query()->create($data);
    }
}
