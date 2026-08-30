<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\BusinessSector;

class CreateBusinessSector
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by BusinessSectorForm::transformServiceData().
     */
    public function handle(array $data): BusinessSector
    {
        return BusinessSector::query()->create($data);
    }
}
