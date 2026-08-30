<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceAttribute;

class CreateServiceAttribute
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     data_type: string,
     *     unit: ?string,
     *     options: ?array<int, string>,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by ServiceAttributeForm::transformServiceData().
     */
    public function handle(array $data): ServiceAttribute
    {
        return ServiceAttribute::query()->create($data);
    }
}
