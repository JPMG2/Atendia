<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\AppointmentBooked;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class BookAppointment
{
    public function __construct(private readonly SlotFinder $slots) {}

    /**
     * Books a slot after checking it is STILL free: the assistant offered the
     * hour seconds ago and two people can ask for the same one at once. The
     * check and the insert run under a lock on that very hour — checking and
     * then writing let both bookers pass when they arrived together.
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
        ?Professional $professional = null,
    ): Appointment {
        $startsAt = $startsAt->setTimezone($business->localTimezone());

        // Per business and hour, not per professional: an "anyone" booking and a named one
        // would otherwise pick the same person at the same time.
        $lock = Cache::lock("agenda:{$business->id}:".$startsAt->utc()->format('YmdHi'), 10);

        // A booker who loses the lock reads the same answer as one who lost the
        // hour: it is gone. Every caller already says so in its own words.
        $appointment = $lock->get(function () use ($business, $customer, $startsAt, $service, $source, $notes, $conversationId, $professional): ?Appointment {
            $attending = $this->attendant($business, $startsAt, $service, $professional);

            if ($attending === false) {
                return null;
            }

            // Stored UTC, read back UTC, painted local: Eloquent writes a Carbon's
            // wall clock as-is, so a local time would land three hours off.
            $appointment = new Appointment([
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes($this->slots->slotMinutes($business, $service, $attending))->utc(),
                'status' => AppointmentStatus::Confirmed,
                'source' => $source,
                'notes' => $notes,
                'conversation_id' => $conversationId,
            ]);

            $appointment->customer()->associate($customer);
            $appointment->service()->associate($service);
            $appointment->professional()->associate($attending);
            $appointment->business()->associate($business)->save();

            return $appointment;
        });

        if (! $appointment instanceof Appointment) {
            throw new RuntimeException('The slot is no longer free.');
        }

        // Only what the business did not do itself: an owner booking from the
        // panel already knows, and a row telling her so is noise.
        if ($source !== AppointmentSource::Owner) {
            (new Panel($appointment, [], AppointmentBooked::class))->send();
        }

        return $appointment;
    }

    /**
     * Who attends the hour: the one asked for, or the first free of those who
     * attend the service; null for a business with nobody set up (its single
     * agenda). False when there is no one to take it.
     */
    private function attendant(Business $business, CarbonImmutable $startsAt, ?Service $service, ?Professional $asked): Professional|false|null
    {
        if ($asked !== null) {
            return $this->slots->isFree($business, $startsAt, $service, null, $asked) ? $asked : false;
        }

        $offered = Professional::offeredFor($business, $service);

        if ($offered->isEmpty()) {
            return $this->slots->isFree($business, $startsAt, $service) ? null : false;
        }

        return $offered->first(fn (Professional $each): bool => $this->slots->isFree($business, $startsAt, $service, null, $each)) ?? false;
    }
}
