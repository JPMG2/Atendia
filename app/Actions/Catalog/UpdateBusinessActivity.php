<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\BusinessActivity;

class UpdateBusinessActivity
{
    /**
     * @param  array{
     *     business_sector_id: int,
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by BusinessActivityForm::transformServiceData().
     */
    public function handle(int $id, array $data): BusinessActivity
    {
        $record = BusinessActivity::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
