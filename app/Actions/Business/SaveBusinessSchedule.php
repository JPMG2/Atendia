<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;

/**
 * The opening-hours slice: the screen always shows the WHOLE week, so the
 * save replaces it whole — shifts have no identity worth reconciling, and
 * a partial write could leave a day half morning-old, half afternoon-new.
 */
class SaveBusinessSchedule
{
    /**
     * @param  array<int, list<array{opens_at: string, closes_at: string}>>  $week
     *                                                                              Shifts keyed by day-of-week (0 = Sunday), already validated by the calling form.
     */
    public function handle(Business $business, array $week): Business
    {
        $business->hours()->delete();

        foreach ($week as $day => $shifts) {
            foreach ($shifts as $shift) {
                $business->hours()->create([
                    'day_of_week' => $day,
                    'opens_at' => $shift['opens_at'],
                    'closes_at' => $shift['closes_at'],
                ]);
            }
        }

        // The cached relation still holds the old week; the next read reloads.
        return $business->unsetRelation('hours');
    }
}
