<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Province;

class CreateProvince
{
    /**
     * Create a province.
     *
     * @param  array{
     *     country_id: int,
     *     name: string,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma ProvinceForm::transformServiceData().
     */
    public function handle(array $data): Province
    {
        return Province::query()->create($data);
    }
}
