<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Enums\AppointmentStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Messaging\WhatsApp\AppointmentCancelledByClosure;
use App\Models\Appointment;
use App\Models\BusinessClosure;
use App\Models\Conversation;
use App\Services\EvolutionApi;
use Illuminate\Database\Eloquent\Collection;

/**
 * The hours someone was holding on a day that is no longer going to happen.
 *
 * The notice is the point and the cancellation its side effect: an hour is
 * cancelled only once its notice left. It is the business's word to its own
 * customer, so it leaves from the business's number, never the platform's.
 */
class CancelAppointmentsForClosure
{
    /** Milliseconds between two notices, so a business with many bookings does not burst from its number. */
    private const int STAGGER_MS = 3000;

    public function __construct(
        private readonly CancelAppointment $cancel,
        private readonly EvolutionApi $evolution,
    ) {}

    /**
     * @return int How many people were told. Nothing is cancelled when the business
     *             may not message customers or has no linked WhatsApp line of its own.
     */
    public function handle(BusinessClosure $closure): int
    {
        $business = $closure->business;

        // The same gate every outgoing message passes: a suspended business does not get to message anyone.
        if (! $business->canMessageCustomers() || ! $business->hasOwnWhatsAppLine()) {
            return 0;
        }

        $told = 0;

        foreach ($this->pending($closure) as $appointment) {
            $phone = preg_replace('/\D+/', '', (string) $appointment->customer?->phone) ?? '';

            // Nobody to tell: the hour is freed all the same.
            if (strlen($phone) < 8) {
                $this->cancel->handle($appointment);

                continue;
            }

            $text = $business->speaking(fn (): string => (new AppointmentCancelledByClosure($appointment, $closure->reason))->text());

            // A failed send leaves the hour standing: she can try again, and the customer is not left at a closed door.
            $sent = rescue(function () use ($business, $phone, $text, $told): bool {
                $this->evolution->sendText((string) $business->whatsapp_instance, $phone, $text, $told * self::STAGGER_MS);

                return true;
            }, false);

            if (! $sent) {
                continue;
            }

            $this->cancel->handle($appointment);
            $told++;

            // In the thread too: a reply to the notice needs the assistant to know why.
            Conversation::query()->where('contact_phone', $appointment->customer->phone)->first()?->messages()->create([
                'direction' => MessageDirection::Out,
                'author' => MessageAuthor::Assistant,
                'body' => $text,
            ]);
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
