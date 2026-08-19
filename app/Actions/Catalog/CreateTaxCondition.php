<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\TaxCondition;

class CreateTaxCondition
{
    /**
     * Create a tax condition.
     *
     * @param  array{
     *     country_id: int,
     *     code: string,
     *     name: string,
     *     discriminate_tax: bool,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma TaxConditionForm::transformServiceData().
     */
    public function handle(array $data): TaxCondition
    {
        return TaxCondition::query()->create($data);
    }
}
