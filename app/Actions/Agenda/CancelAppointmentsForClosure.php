<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Enums\AppointmentStatus;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\AppointmentCancelledByClosure;
use App\Models\Appointment;
use App\Models\BusinessClosure;
use Illuminate\Database\Eloquent\Collection;

/**
 * The hours someone was holding on a day that is no longer going to happen.
 *
 * Cancelling without telling them is how a person shows up to a closed door,
 * so the notice is the point and the cancellation is the side effect.
 */
class CancelAppointmentsForClosure
{
    public function __construct(private readonly CancelAppointment $cancel) {}

    /** @return int How many people were told */
    public function handle(BusinessClosure $closure): int
    {
        $business = $closure->business;

        // The same gate every outgoing message passes: a suspended business
        // does not get to message anyone.
        if (! $business->canMessageCustomers()) {
            return 0;
        }

        $told = 0;

        foreach ($this->pending($closure) as $appointment) {
            $this->cancel->handle($appointment);

            $phone = preg_replace('/\D+/', '', (string) $appointment->customer?->phone) ?? '';

            if (strlen($phone) < 8) {
                continue;
            }

            $sent = $business->speaking(fn (): bool => new WhatsApp(
                $appointment,
                [$phone],
                AppointmentCancelledByClosure::class,
                [$closure->reason],
            )->send());

            $told += $sent ? 1 : 0;
        }

        return $told;
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
