<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Currency;

class CreateCurrency
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     symbol: string,
     *     decimal_places: int,
     *     is_active: bool
     * }  $data  Already validated by CurrencyForm::transformServiceData().
     */
    public function handle(array $data): Currency
    {
        return Currency::query()->create($data);
    }
}
