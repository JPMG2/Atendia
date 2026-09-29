<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use RuntimeException;

class BookAppointment
{
    public function __construct(private readonly SlotFinder $slots) {}

    /**
     * Books a slot after checking it is STILL free: the assistant offered the
     * hour seconds ago and two people can ask for the same one at once.
     *
     * @throws RuntimeException when the hour is already gone
     */
    public function handle(
        Business $business,
        Customer $customer,
        CarbonImmutable $startsAt,
        ?Service $service = null,
        AppointmentSource $source = AppointmentSource::Assistant,
        ?string $notes = null,
        ?int $conversationId = null,
    ): Appointment {
        $startsAt = $startsAt->setTimezone($business->localTimezone());

        if (! $this->slots->isFree($business, $startsAt, $service)) {
            throw new RuntimeException('The slot is no longer free.');
        }

        // Stored UTC, read back UTC, painted local: Eloquent writes a Carbon's
        // wall clock as-is, so a local time would land three hours off.
        $appointment = new Appointment([
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes($this->slots->slotMinutes($business, $service))->utc(),
            'status' => AppointmentStatus::Confirmed,
            'source' => $source,
            'notes' => $notes,
            'conversation_id' => $conversationId,
        ]);

        $appointment->customer()->associate($customer);
        $appointment->service()->associate($service);
        $appointment->business()->associate($business)->save();

        return $appointment;
    }
}
