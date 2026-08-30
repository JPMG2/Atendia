<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceModality;

class CreateServiceModality
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     icon: ?string,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by ServiceModalityForm::transformServiceData().
     */
    public function handle(array $data): ServiceModality
    {
        return ServiceModality::query()->create($data);
    }
}
