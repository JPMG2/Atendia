<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\BusinessActivity;

class CreateBusinessActivity
{
    /**
     * Create a business activity.
     *
     * @param  array{
     *     business_sector_id: int,
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma BusinessActivityForm::transformServiceData().
     */
    public function handle(array $data): BusinessActivity
    {
        return BusinessActivity::query()->create($data);
    }
}
