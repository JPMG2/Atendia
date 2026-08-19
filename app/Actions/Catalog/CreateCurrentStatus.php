<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CurrentStatus;

class CreateCurrentStatus
{
    /**
     * Create a status.
     *
     * @param  array{
     *     name: string
     * }  $data  Payload YA validado, tal como lo arma CurrentStatusForm::transformServiceData().
     */
    public function handle(array $data): CurrentStatus
    {
        return CurrentStatus::query()->create($data);
    }
}
