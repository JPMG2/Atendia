<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceAttribute;

class UpdateServiceAttribute
{
    /**
     * Update a service attribute.
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
    public function handle(int $id, array $data): ServiceAttribute
    {
        $attribute = ServiceAttribute::query()->findOrFail($id);
        $attribute->update($data);

        return $attribute;
    }
}
