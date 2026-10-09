<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CountryHoliday;

class UpdateCountryHoliday
{
    /**
     * @param  array<string, mixed>  $data  Already validated by CountryHolidayForm.
     */
    public function handle(int $id, array $data): CountryHoliday
    {
        $holiday = CountryHoliday::query()->findOrFail($id);
        $holiday->update($data);

        return $holiday;
    }
}
