<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Province;

class UpdateProvince
{
    /**
     * @param  array{
     *     country_id: int,
     *     name: string,
     *     is_active: bool
     * }  $data  Already validated by ProvinceForm::transformServiceData().
     */
    public function handle(int $id, array $data): Province
    {
        $record = Province::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
