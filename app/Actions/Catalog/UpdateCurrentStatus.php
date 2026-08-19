<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CurrentStatus;

class UpdateCurrentStatus
{
    /**
     * Update a status.
     *
     * @param  array{
     *     name: string
     * }  $data  Payload YA validado, tal como lo arma CurrentStatusForm::transformServiceData().
     */
    public function handle(int $id, array $data): CurrentStatus
    {
        $record = CurrentStatus::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
