<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\BusinessSector;

class UpdateBusinessSector
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
    public function handle(int $id, array $data): BusinessSector
    {
        $record = BusinessSector::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
