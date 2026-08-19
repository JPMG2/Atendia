<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Region;

class CreateRegion
{
    /**
     * Create a region.
     *
     * @param  array{
     *     province_id: int,
     *     name: string,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma RegionForm::transformServiceData().
     */
    public function handle(array $data): Region
    {
        return Region::query()->create($data);
    }
}
