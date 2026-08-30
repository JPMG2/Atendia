<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceType;

class CreateServiceType
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
    public function handle(array $data): ServiceType
    {
        return ServiceType::query()->create($data);
    }
}
