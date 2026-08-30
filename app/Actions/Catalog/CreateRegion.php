<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Region;

class CreateRegion
{
    /**
     * @param  array{
     *     province_id: int,
     *     name: string,
     *     is_active: bool
     * }  $data  Already validated by RegionForm::transformServiceData().
     */
    public function handle(array $data): Region
    {
        return Region::query()->create($data);
    }
}
