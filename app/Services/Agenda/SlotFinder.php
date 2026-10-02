<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Service;
use Carbon\CarbonImmutable;

/**
 * The free hours of the agenda: the opening shifts minus what is already
 * booked, in slots as long as the service lasts.
 *
 * ONE definition for every caller — a second copy of this arithmetic would
 * offer hours the booking action then refuses.
 */
class SlotFinder
{
    /**
     * Free starting times of one day, in the business's own timezone.
     *
     * @return list<CarbonImmutable>
     */
    public function freeSlots(Business $business, CarbonImmutable $day, ?Service $service = null, ?CarbonImmutable $now = null, ?int $ignoringId = null): array
    {
        if (! $business->appointments_enabled) {
            return [];
        }

        $timezone = $business->localTimezone();
        $day = $day->setTimezone($timezone)->startOfDay();

        // A day she marked closed offers nothing, whatever the weekly hours
        // say: the holiday has to reach the customer asking for an hour.
        $closure = $business->closureOn($day);

        if ($closure?->isFullDay() === true) {
            return [];
        }

        $special = $closure;
        $now ??= CarbonImmutable::now($timezone);
        $minutes = $this->slotMinutes($business, $service);

        // A night shift ends on the next calendar day, so what is already
        // booked in those early hours has to come along or it gets offered twice.
        $dayEnd = $business->hours
            ->where('day_of_week', (int) $day->format('w'))
            ->contains(fn (BusinessHour $shift): bool => $shift->runsPastMidnight())
                ? $day->addDay()->endOfDay()
                : $day->endOfDay();

        // A booking being MOVED must not block its own new hour.
        $booked = Appointment::between($business, $day, $dayEnd)
            ->reject(fn (Appointment $taken): bool => $taken->id === $ignoringId);

        // The daily cap is the whole day's ceiling, whatever hour is asked for.
        $cap = $business->appointments_per_day;

        if ($cap !== null && $booked->count() >= $cap) {
            return [];
        }

        $capacity = max(1, (int) $business->appointment_capacity);
        $slots = [];

        // Special hours REPLACE the weekly ones for that day: offering both
        // would hand out the very hours she decided not to work.
        $shifts = $special === null
            ? $business->hours->where('day_of_week', (int) $day->format('w'))
            : collect([new BusinessHour(['opens_at' => $special->opens_at, 'closes_at' => $special->closes_at])]);

        foreach ($shifts as $shift) {
            $opens = $this->at($day, (string) $shift->opens_at);
            $closes = $this->at($day, (string) $shift->closes_at);

            if ($shift->runsPastMidnight()) {
                $closes = $closes->addDay();
            }

            for ($slot = $opens; $slot->addMinutes($minutes)->lessThanOrEqualTo($closes); $slot = $slot->addMinutes($minutes)) {
                if ($slot->lessThanOrEqualTo($now)) {
                    continue;
                }

                $ends = $slot->addMinutes($minutes);
                $overlapping = $booked->filter(
                    fn (Appointment $taken): bool => $taken->starts_at->setTimezone($timezone)->lessThan($ends)
                        && $taken->ends_at->setTimezone($timezone)->greaterThan($slot)
                )->count();

                if ($overlapping < $capacity) {
                    $slots[] = $slot;
                }
            }
        }

        usort($slots, fn (CarbonImmutable $left, CarbonImmutable $right): int => $left <=> $right);

        return $slots;
    }

    /**
     * The first free slots from a moment on, walking forward day by day: what
     * answers "¿cuándo tienen turno?" without a date.
     *
     * @return list<CarbonImmutable>
     */
    public function nextSlots(Business $business, ?Service $service, CarbonImmutable $from, int $limit = 3, int $daysAhead = 14): array
    {
        $timezone = $business->localTimezone();
        $from = $from->setTimezone($timezone);
        $found = [];

        for ($offset = 0; $offset <= $daysAhead && count($found) < $limit; $offset++) {
            foreach ($this->freeSlots($business, $from->addDays($offset), $service, $from) as $slot) {
                $found[] = $slot;

                if (count($found) === $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    /** Whether that exact hour is still bookable — checked again when saving. */
    public function isFree(Business $business, CarbonImmutable $startsAt, ?Service $service = null, ?int $ignoringId = null): bool
    {
        $startsAt = $startsAt->setTimezone($business->localTimezone());

        return collect($this->freeSlots($business, $startsAt, $service, $startsAt->subSecond(), $ignoringId))
            ->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startsAt));
    }

    /** The service's own duration, or the business's default for a plain slot. */
    public function slotMinutes(Business $business, ?Service $service): int
    {
        return $service?->duration_minutes
            ?? max(5, (int) $business->appointment_slot_minutes);
    }

    /** "09:30" or "09:30:00" onto the day being walked, in the business's timezone. */
    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }
}
