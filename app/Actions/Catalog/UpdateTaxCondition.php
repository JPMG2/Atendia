<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\TaxCondition;

class UpdateTaxCondition
{
    /**
     * Update a tax condition.
     *
     * @param  array{
     *     country_id: int,
     *     code: string,
     *     name: string,
     *     discriminate_tax: bool,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma TaxConditionForm::transformServiceData().
     */
    public function handle(int $id, array $data): TaxCondition
    {
        $record = TaxCondition::query()->findOrFail($id);
        $record->update($data);

        return $record;
    }
}
