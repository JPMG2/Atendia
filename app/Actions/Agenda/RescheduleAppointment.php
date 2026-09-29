<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Models\Appointment;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use RuntimeException;

class RescheduleAppointment
{
    public function __construct(private readonly SlotFinder $slots) {}

    /**
     * Moves a booking to another hour, keeping its own slot out of the way:
     * without that, an appointment could never move within its own hour.
     *
     * @throws RuntimeException when the new hour is not free
     */
    public function handle(Appointment $appointment, CarbonImmutable $startsAt): Appointment
    {
        $business = $appointment->business;
        $startsAt = $startsAt->setTimezone($business->localTimezone());

        if (! $this->slots->isFree($business, $startsAt, $appointment->service, $appointment->id)) {
            throw new RuntimeException('The new slot is not free.');
        }

        $appointment->update([
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes($this->slots->slotMinutes($business, $appointment->service))->utc(),
            'reminder_sent_at' => null,
        ]);

        return $appointment;
    }
}
