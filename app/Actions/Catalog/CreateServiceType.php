<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceType;

class CreateServiceType
{
    /**
     * Create a service type.
     *
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     service_modality_id: int,
     *     business_sector_id: ?int,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma ServiceTypeForm::transformServiceData().
     */
    public function handle(array $data): ServiceType
    {
        return ServiceType::query()->create($data);
    }
}
