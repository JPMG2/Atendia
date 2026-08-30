<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Country;

class CreateCountry
{
    /**
     * @param  array{
     *     currency_id: int,
     *     name: string,
     *     code: string,
     *     phone_code: string|null,
     *     is_active: bool
     * }  $data  Already validated by CountryForm::transformServiceData().
     */
    public function handle(array $data): Country
    {
        return Country::query()->create($data);
    }
}
