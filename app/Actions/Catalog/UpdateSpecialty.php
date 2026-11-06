<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Specialty;

class UpdateSpecialty
{
    /**
     * @param  array{
     *     business_activity_id: int,
     *     code: string,
     *     name: string,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by SpecialtyForm::transformServiceData().
     */
    public function handle(int $id, array $data): Specialty
    {
        $record = Specialty::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
