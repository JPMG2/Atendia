<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessSchedule;
use App\Models\Business;
use App\Models\BusinessHour;

/**
 * The opening-hours piece: a day holds several shifts — the split day LatAm
 * businesses actually keep.
 */
class Schedule
{
    public function __construct(private Business $business) {}

    /**
     * The week as the screens paint it: Monday first, closed days as empty
     * lists, 0 = Sunday like date("w").
     *
     * @var array<int, list<BusinessHour>>
     */
    public array $week {
        get {
            $byDay = $this->business->hours->groupBy('day_of_week');

            return collect([1, 2, 3, 4, 5, 6, 0])
                ->mapWithKeys(fn (int $day): array => [$day => ($byDay->get($day) ?? collect())->values()->all()])
                ->all();
        }
    }

    /** One declared shift is enough: an always-closed business has no hours. */
    public bool $isComplete {
        get => $this->business->hours->isNotEmpty();
    }

    /**
     * @param  array<int, list<array{opens_at: string, closes_at: string}>>  $week
     */
    public function save(array $week): Business
    {
        return app(SaveBusinessSchedule::class)->handle($this->business, $week);
    }
}
