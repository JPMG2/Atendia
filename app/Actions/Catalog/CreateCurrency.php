<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Currency;

class CreateCurrency
{
    /**
     * Create a currency.
     *
     * @param  array{
     *     code: string,
     *     name: string,
     *     symbol: string,
     *     decimal_places: int,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma CurrencyForm::transformServiceData().
     */
    public function handle(array $data): Currency
    {
        return Currency::query()->create($data);
    }
}
