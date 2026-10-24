<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Classes\Main\Closures;
use App\Models\Business;

/**
 * The country's national holidays still ahead, as closures, in one click.
 * Whatever she already loaded by hand wins: nothing here overwrites her own decision.
 */
class ImportCountryHolidays
{
    /**
     * @return int|null How many were added; null when the business has no country.
     */
    public function handle(Business $business): ?int
    {
        if ($business->country === null) {
            return null;
        }

        $closures = new Closures($business);
        $added = 0;

        foreach ($closures->holidaysAhead as $holiday) {
            $date = $holiday['date']->format('Y-m-d');

            if ($closures->covers($date, $date)) {
                continue;
            }

            $business->closures()->create(['starts_on' => $date, 'ends_on' => $date, 'reason' => $holiday['name']]);

            $added++;
        }

        return $added;
    }
}
