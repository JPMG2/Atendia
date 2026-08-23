<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceAttribute;

class CreateServiceAttribute
{
    /**
     * Create a service attribute.
     *
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     data_type: string,
     *     unit: ?string,
     *     options: ?array<int, string>,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma ServiceAttributeForm::transformServiceData().
     */
    public function handle(array $data): ServiceAttribute
    {
        return ServiceAttribute::query()->create($data);
    }
}
