<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CurrentStatus;

class UpdateCurrentStatus
{
    /**
     * @param  array{
     *     name: string
     * }  $data  Already validated by CurrentStatusForm::transformServiceData().
     */
    public function handle(int $id, array $data): CurrentStatus
    {
        $record = CurrentStatus::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
