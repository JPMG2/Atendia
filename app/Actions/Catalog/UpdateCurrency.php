<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Currency;

class UpdateCurrency
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
    public function handle(int $id, array $data): Currency
    {
        $currency = Currency::query()->findOrFail($id);
        $currency->update($data);

        return $currency;
    }
}
