<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One national holiday of one country: a fixed date, or a day counted from
 * Easter Sunday. Shared by every business of that country, so it carries no
 * `business_id` — like {@see Country} itself.
 */
#[Fillable(['country_id', 'name', 'month', 'day', 'easter_offset'])]
class CountryHoliday extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'day' => 'integer',
            'easter_offset' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The country's holidays landed on a year, soonest first.
     *
     * @return Collection<int, array{date: CarbonImmutable, name: string}>
     */
    public static function forYear(int $countryId, int $year): Collection
    {
        $easter = self::easter($year);

        return self::query()
            ->where('country_id', $countryId)
            ->orderBy('month')
            ->orderBy('day')
            ->get()
            ->map(fn (self $holiday): array => [
                'date' => $holiday->easter_offset !== null
                    ? $easter->addDays($holiday->easter_offset)
                    : CarbonImmutable::create($year, $holiday->month, $holiday->day),
                'name' => $holiday->name,
            ])
            ->sortBy(fn (array $holiday): string => $holiday['date']->format('Y-m-d'))
            ->values();
    }

    /**
     * Easter Sunday by the anonymous Gregorian algorithm. Computed and not
     * stored: it is the same arithmetic every year, and a stored table would
     * be one more thing that runs out.
     */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
