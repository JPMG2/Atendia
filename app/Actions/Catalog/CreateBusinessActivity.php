<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\BusinessActivity;
use App\Models\BusinessSector;

class CreateBusinessActivity
{
    /**
     * @param  array{
     *     business_sector_id: int,
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     sort_order: int,
     *     is_active: bool,
     *     catalog_eligible: bool
     * }  $data  Already validated by BusinessActivityForm::transformServiceData().
     */
    public function handle(array $data): BusinessActivity
    {
        // Born outside Meta's catalog whatever the form said: turning it on is an
        // explicit edit afterwards, never a default she did not look at.
        if (BusinessSector::query()->whereKey($data['business_sector_id'])->value('code') === BusinessSector::HEALTH) {
            $data['catalog_eligible'] = false;
        }

        return BusinessActivity::query()->create($data);
    }
}
