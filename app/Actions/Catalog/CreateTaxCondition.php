<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\TaxCondition;

class CreateTaxCondition
{
    /**
     * @param  array{
     *     country_id: int,
     *     code: string,
     *     name: string,
     *     discriminate_tax: bool,
     *     is_active: bool
     * }  $data  Already validated by TaxConditionForm::transformServiceData().
     */
    public function handle(array $data): TaxCondition
    {
        return TaxCondition::query()->create($data);
    }
}
