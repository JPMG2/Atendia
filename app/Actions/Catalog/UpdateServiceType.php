<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceType;

class UpdateServiceType
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     service_modality_id: int,
     *     business_sector_id: ?int,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by ServiceTypeForm::transformServiceData().
     */
    public function handle(int $id, array $data): ServiceType
    {
        $type = ServiceType::query()->findOrFail($id);
        $type->update($data);

        return $type;
    }
}
