<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CountryHoliday;
use Illuminate\Support\Facades\DB;

class CopyCountryHolidays
{
    /**
     * Gives `$targetId` the recurring holidays of `$sourceId` it does not have.
     *
     * @return int How many rows were created.
     */
    public function handle(int $sourceId, int $targetId): int
    {
        return DB::transaction(function () use ($sourceId, $targetId): int {
            $holidays = CountryHoliday::copyableFrom($sourceId, $targetId);

            $holidays->each(fn (CountryHoliday $holiday): CountryHoliday => CountryHoliday::query()->create([
                'country_id' => $targetId,
                'name' => $holiday->name,
                'month' => $holiday->month,
                'day' => $holiday->day,
                'easter_offset' => $holiday->easter_offset,
                'on_date' => null,
                'is_active' => true,
            ]));

            return $holidays->count();
        });
    }
}
