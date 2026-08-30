<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Region;

class UpdateRegion
{
    /**
     * @param  array{
     *     province_id: int,
     *     name: string,
     *     is_active: bool
     * }  $data  Already validated by RegionForm::transformServiceData().
     */
    public function handle(int $id, array $data): Region
    {
        $record = Region::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
