<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ServiceModality;

class UpdateServiceModality
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
    public function handle(int $id, array $data): ServiceModality
    {
        $modality = ServiceModality::query()->findOrFail($id);
        $modality->update($data);

        return $modality;
    }
}
