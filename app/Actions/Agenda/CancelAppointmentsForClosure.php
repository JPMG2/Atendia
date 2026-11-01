<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Enums\AppointmentStatus;
use App\Jobs\TellClosureBooking;
use App\Models\Appointment;
use App\Models\BusinessClosure;
use App\Services\ProactiveSends;
use Illuminate\Database\Eloquent\Collection;

/**
 * The hours someone was holding on a day that is no longer going to happen.
 * An hour is cancelled only once its notice left (`TellClosureBooking`), from
 * the business's own number. The customer did not just write, so it passes the
 * outbound gate like a reminder: written or consented, under the day's cap,
 * never to someone who stopped answering, spaced by a random gap.
 */
class CancelAppointmentsForClosure
{
    public function __construct(private readonly CancelAppointment $cancel) {}

    /**
     * Nothing is queued when the business may not message customers or has no
     * linked WhatsApp line of its own.
     *
     * @return array{queued: int, unreachable: int} People with a notice on its way, and the people the gate
     *                                              held back (their hours stay: she calls them).
     */
    public function handle(BusinessClosure $closure): array
    {
        $business = $closure->business;

        // The same gate every outgoing message passes: a suspended business does not get to message anyone.
        if (! $business->canMessageCustomers() || ! $business->hasOwnWhatsAppLine()) {
            return ['queued' => 0, 'unreachable' => 0];
        }

        $gate = ProactiveSends::for($business);
        $room = $gate->leftToday();
        $queued = 0;
        $unreachable = 0;
        $delay = 0;

        // One notice per PERSON: two near-identical messages seconds apart is what gets a number banned.
        $byPerson = $this->pending($closure)->groupBy(fn (Appointment $appointment): string => preg_replace('/\D+/', '', (string) $appointment->customer?->phone) ?? '');

        foreach ($byPerson as $phone => $bookings) {
            // Nobody to tell: the hours are freed all the same.
            if (strlen((string) $phone) < 8) {
                $bookings->each(fn (Appointment $appointment) => $this->cancel->handle($appointment));

                continue;
            }

            $customer = $bookings->first()->customer;

            if ($customer->blocked_at !== null || $gate->refusal((string) $customer->phone, $customer) !== null || $queued >= $room) {
                $unreachable++;

                continue;
            }

            TellClosureBooking::dispatch($business->id, $bookings->pluck('id')->sort()->values()->all(), $closure->reason)->delay(now()->addSeconds($delay));
            $queued++;
            $delay += ProactiveSends::gapSeconds();
        }

        return ['queued' => $queued, 'unreachable' => $unreachable];
    }

    /**
     * What is still standing inside the closed days: a cancelled or attended
     * hour has nobody waiting on it.
     *
     * @return Collection<int, Appointment>
     */
    public function pending(BusinessClosure $closure): Collection
    {
        return Appointment::query()
            ->where('business_id', $closure->business_id)
            ->where('status', AppointmentStatus::Confirmed)
            ->whereDate('starts_at', '>=', $closure->starts_on->format('Y-m-d'))
            ->whereDate('starts_at', '<=', $closure->ends_on->format('Y-m-d'))
            ->with('customer')
            ->get();
    }
}
