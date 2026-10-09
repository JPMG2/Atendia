<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\AdoptionNudge;

class UpdateAdoptionNudge
{
    /**
     * @param  array{step: string, subject: string, body: string, is_active: bool}  $data  Already validated by AdoptionNudgeForm.
     */
    public function handle(int $id, array $data): AdoptionNudge
    {
        $record = AdoptionNudge::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
