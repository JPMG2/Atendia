<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CountryHoliday;
use Carbon\CarbonImmutable;

class MarkBridgeRange
{
    /** A bridge is a few days: anything longer is a slip of the drag, not a decision. */
    public const int MAX_DAYS = 31;

    /**
     * Marks every free weekday between two dates (both included) as a
     * one-year bridge holiday of the country. Weekends and days that are
     * already holidays are left alone; a switched-off bridge in the range is
     * brought back, never duplicated.
     *
     * @return list<string> The dates that now count as a bridge because of this call.
     */
    public function handle(int $countryId, string $from, string $to): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);

        $taken = collect(range($start->year, $end->year))
            ->flatMap(fn (int $year) => CountryHoliday::forYear($countryId, $year))
            ->map(fn (array $holiday): string => $holiday['date']->format('Y-m-d'))
            ->flip();

        $marked = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $date = $day->format('Y-m-d');

            if ($day->isWeekend() || $taken->has($date)) {
                continue;
            }

            $existing = CountryHoliday::onceOn($countryId, $date);

            if ($existing === null) {
                CountryHoliday::query()->create([
                    'country_id' => $countryId,
                    'name' => __('catalog.country_holiday.bridge.name'),
                    'on_date' => $date,
                    'is_active' => true,
                ]);
            } elseif (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            $marked[] = $date;
        }

        return $marked;
    }
}
