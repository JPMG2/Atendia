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
    /** Monday first, 0 = Sunday like date("w"). */
    public const array DAYS = [1, 2, 3, 4, 5, 6, 0];

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

            return collect(self::DAYS)
                ->mapWithKeys(fn (int $day): array => [$day => ($byDay->get($day) ?? collect())->values()->all()])
                ->all();
        }
    }

    /**
     * The stored week as the screens type it. Postgres hands times back with
     * seconds, which the inputs and the comparison must not see.
     *
     * @var array<int, list<array{opens_at: string, closes_at: string}>>
     */
    public array $times {
        get => collect($this->week)
            ->map(fn (array $shifts): array => array_map(
                fn (BusinessHour $shift): array => ['opens_at' => substr($shift->opens_at, 0, 5), 'closes_at' => substr($shift->closes_at, 0, 5)],
                $shifts,
            ))
            ->all();
    }

    /**
     * The week as the assistant knows it ("Lunes: 09:00 a 13:00 y 14:00 a 18:00"):
     * the very lines published to its knowledge base on every save.
     *
     * @var list<string>
     */
    public array $spoken {
        get => $this->business->scheduleLines();
    }

    /**
     * The one set of rules the web's form and the app's endpoint both validate with.
     * No "after": a shift CAN close before it opens — the night shift closing the
     * next day. Equal times mean nothing; overlap is checked after this.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'week' => ['array'],
            // A list: the shifts are ordered, and a keyed object would break the overlap check that reads them by position.
            'week.*' => ['array', 'list'],
            'week.*.*.opens_at' => ['required', 'date_format:H:i'],
            'week.*.*.closes_at' => ['required', 'date_format:H:i', 'different:week.*.*.opens_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'week.*.*.opens_at' => config('nicename.opens_at'),
            'week.*.*.closes_at' => config('nicename.closes_at'),
        ];
    }

    /**
     * Two shifts that step on each other make the SAME hour be offered twice —
     * to her in the agenda and to her customer over WhatsApp. The error lands
     * on the second shift, which is the one she has to move. Touching ends
     * (09–12 then 12–18) are fine; only a real overlap collides.
     *
     * @param  array<int, list<array{opens_at: string, closes_at: string}>>  $week
     * @return array<string, string> Message per `week.{day}.{index}.opens_at`.
     */
    public static function overlapErrors(array $week): array
    {
        // Every shift laid on one week of minutes, Monday first: a shift that
        // closes after midnight runs into the NEXT day, so Monday 22:00–02:00
        // meets Tuesday 01:00–05:00 and no longer meets Monday's own morning.
        $placed = [];

        foreach (self::DAYS as $position => $day) {
            foreach ($week[$day] ?? [] as $index => $shift) {
                $opens = BusinessHour::minutes($shift['opens_at']);
                $closes = BusinessHour::minutes($shift['closes_at']);
                $start = $position * 1440 + $opens;

                $placed[] = ['key' => "week.{$day}.{$index}.opens_at", 'day' => $day, 'shift' => $shift, 'start' => $start, 'end' => $start + ($closes > $opens ? $closes - $opens : 1440 - $opens + $closes)];
            }
        }

        $errors = [];

        foreach ($placed as $at => $current) {
            foreach (array_slice($placed, 0, $at) as $earlier) {
                if (self::meet($earlier, $current)) {
                    $shift = $earlier['shift']['opens_at'].'–'.$earlier['shift']['closes_at'];

                    // Another day's shift is named with its day, or "el de 22:00–02:00" reads as one of this day.
                    $errors[$current['key']] = $earlier['day'] === $current['day']
                        ? __('client.business.hours.overlaps', ['shift' => $shift])
                        : __('client.business.hours.overlaps_day', ['day' => __('client.business.hours.day_names.'.$earlier['day']), 'shift' => $shift]);

                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Whether two placed shifts share a minute, the week being a circle:
     * Sunday's night shift runs into Monday morning.
     *
     * @param  array{start: int, end: int}  $a
     * @param  array{start: int, end: int}  $b
     */
    private static function meet(array $a, array $b): bool
    {
        foreach ([-10080, 0, 10080] as $shift) {
            if ($b['end'] > $a['start'] + $shift && $b['start'] < $a['end'] + $shift) {
                return true;
            }
        }

        return false;
    }

    /**
     * Built from the known day keys and the two time fields only: an id or a
     * day injected into the input can never reach the table.
     *
     * @param  array<int|string, mixed>  $week
     * @return array<int, list<array{opens_at: string, closes_at: string}>>
     */
    public function normalized(array $week): array
    {
        $clean = [];

        foreach (self::DAYS as $day) {
            $clean[$day] = array_map(
                fn (array $shift): array => ['opens_at' => $shift['opens_at'], 'closes_at' => $shift['closes_at']],
                array_values($week[$day] ?? []),
            );
        }

        return $clean;
    }

    /**
     * @param  array<int, list<array{opens_at: string, closes_at: string}>>  $week  As returned by {@see normalized()}.
     */
    public function differsFrom(array $week): bool
    {
        return $week !== $this->times;
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
