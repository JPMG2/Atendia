<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\BusinessHour;
use Carbon\CarbonImmutable;

/**
 * A weekly opening schedule with ONE shift per day, as the company keeps it.
 *
 * Pure on purpose: the footer, the preview and the tests all ask the same
 * object, so "open now" cannot mean two things. Shift arithmetic is
 * {@see BusinessHour}'s, which already decides what 22:00–02:00 means.
 */
final class OpeningHours
{
    /** Monday first, the way the screens paint a week; 0 = Sunday like date("w"). */
    public const array DAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

    private const string DEFAULT_OPENS = '09:00';

    private const string DEFAULT_CLOSES = '18:00';

    /**
     * @param  array<int, array{0: string, 1: string}>  $week  Day (0 = Sunday) to [opens, closes]; a day not listed is closed.
     */
    public function __construct(
        public readonly array $week,
        public readonly string $timezone,
    ) {}

    /** No shift on any day: closed all week, which is a statement, not a blank. */
    public bool $isClosedAllWeek {
        get => $this->week === [];
    }

    /**
     * The week in words, runs of equal days folded: "Lunes a viernes de
     * 09:00 a 18:00 h · Sábado de 09:00 a 13:00 h".
     */
    public string $summary {
        get {
            if ($this->week === []) {
                return __('company.hours.closed');
            }

            $days = BusinessHour::dayNames();

            return collect($this->runs())
                ->map(function (array $run) use ($days): string {
                    // Only the first word opens the phrase: "Lunes a viernes".
                    $first = $days[$run['days'][0]];
                    $last = mb_strtolower($days[$run['days'][array_key_last($run['days'])]]);

                    $label = match (count($run['days'])) {
                        1 => $first,
                        2 => __('company.hours.pair', ['first' => $first, 'last' => $last]),
                        default => __('company.hours.span', ['first' => $first, 'last' => $last]),
                    };

                    return __('company.hours.shift', [
                        'days' => $label,
                        'opens' => $run['shift'][0],
                        'closes' => $run['shift'][1],
                    ]);
                })
                ->implode(' · ');
        }
    }

    /**
     * Cleans what the database or a form hands over: only well-formed H:i
     * shifts on real days survive, so a half-typed day reads as closed.
     *
     * @param  array<array-key, mixed>|null  $raw
     * @return array<int, array{0: string, 1: string}>
     */
    public static function normalize(?array $raw): array
    {
        $week = [];

        foreach ((array) $raw as $day => $shift) {
            if (! is_array($shift) || ! in_array((int) $day, self::DAY_ORDER, true)) {
                continue;
            }

            [$opens, $closes] = [array_values($shift)[0] ?? null, array_values($shift)[1] ?? null];

            if (self::isTime($opens) && self::isTime($closes)) {
                $week[(int) $day] = [(string) $opens, (string) $closes];
            }
        }

        return $week;
    }

    /**
     * The week as a form edits it: all seven days, each with a switch and a
     * shift. A closed day keeps the default shift, so switching it on shows
     * 09:00–18:00 and not two empty boxes. Takes either shape: the stored
     * one (day => [opens, closes]) or its own (day => {open, opens_at, ...}).
     *
     * @param  array<array-key, mixed>|null  $raw
     * @return array<int, array{open: bool, opens_at: string, closes_at: string}>
     */
    public static function formState(?array $raw): array
    {
        $state = [];

        foreach (self::DAY_ORDER as $day) {
            $item = is_array($raw[$day] ?? null) ? $raw[$day] : null;
            $shift = array_values($item ?? []);

            $state[$day] = match (true) {
                $item !== null && array_key_exists('open', $item) => [
                    'open' => (bool) $item['open'],
                    'opens_at' => (string) ($item['opens_at'] ?? self::DEFAULT_OPENS),
                    'closes_at' => (string) ($item['closes_at'] ?? self::DEFAULT_CLOSES),
                ],
                $item !== null => [
                    'open' => true,
                    'opens_at' => (string) ($shift[0] ?? self::DEFAULT_OPENS),
                    'closes_at' => (string) ($shift[1] ?? self::DEFAULT_CLOSES),
                ],
                default => ['open' => false, 'opens_at' => self::DEFAULT_OPENS, 'closes_at' => self::DEFAULT_CLOSES],
            };
        }

        return $state;
    }

    /**
     * Monday's shift written onto Tuesday to Friday, open or closed: the same
     * button the business's own hours have, so a week is typed once, not five times.
     *
     * @param  array<array-key, mixed>  $state
     * @return array<int, array{open: bool, opens_at: string, closes_at: string}>
     */
    public static function applyWeekdays(array $state): array
    {
        $week = self::formState($state);

        foreach ([2, 3, 4, 5] as $day) {
            $week[$day] = $week[1];
        }

        return $week;
    }

    /**
     * What gets stored: the open days only, times exactly as typed so the
     * validator can say which box is wrong instead of a day vanishing.
     *
     * @param  array<array-key, mixed>  $state
     * @return array<int, array{0: string, 1: string}>
     */
    public static function toStored(array $state): array
    {
        $stored = [];

        foreach (self::formState($state) as $day => $item) {
            if ($item['open']) {
                $stored[$day] = [trim($item['opens_at']), trim($item['closes_at'])];
            }
        }

        return $stored;
    }

    /** Whether the moment falls inside a shift, in the company's own clock. */
    public function isOpenAt(CarbonImmutable $moment): bool
    {
        $local = $moment->setTimezone($this->timezone);
        $minute = $local->hour * 60 + $local->minute;

        $today = $this->week[$local->dayOfWeek] ?? null;

        if ($today !== null) {
            [$opens, $closes] = BusinessHour::span($today[0], $today[1]);

            if ($minute >= $opens && $minute < $closes) {
                return true;
            }
        }

        // The tail of yesterday's night shift is still today's morning.
        $yesterday = $this->week[($local->dayOfWeek + 6) % 7] ?? null;

        if ($yesterday !== null) {
            [, $closes] = BusinessHour::span($yesterday[0], $yesterday[1]);

            return $minute < $closes - 1440;
        }

        return false;
    }

    /**
     * Consecutive days that share one shift, in the order of the week.
     *
     * @return list<array{days: list<int>, shift: array{0: string, 1: string}}>
     */
    private function runs(): array
    {
        $runs = [];

        foreach (self::DAY_ORDER as $day) {
            $shift = $this->week[$day] ?? null;

            if ($shift === null) {
                $runs[] = null;

                continue;
            }

            $last = array_key_last($runs);

            if ($last !== null && $runs[$last] !== null && $runs[$last]['shift'] === $shift) {
                $runs[$last]['days'][] = $day;
            } else {
                $runs[] = ['days' => [$day], 'shift' => $shift];
            }
        }

        return array_values(array_filter($runs));
    }

    private static function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
