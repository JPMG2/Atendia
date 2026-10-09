<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CountryHoliday;

class CreateCountryHoliday
{
    /**
     * @param  array<string, mixed>  $data  Already validated by CountryHolidayForm.
     */
    public function handle(array $data): CountryHoliday
    {
        return CountryHoliday::query()->create($data);
    }
}
