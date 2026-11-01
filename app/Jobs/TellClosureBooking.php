<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Agenda\CancelAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\ClosureNoticeHeld;
use App\Messaging\WhatsApp\AppointmentCancelledByClosure;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Services\EvolutionApi;
use App\Services\ProactiveSends;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * One person told that their hours on a closed day are gone, in one message.
 * Queued apart and spaced from the number's last real send, so a closure with
 * many bookings never bursts from a number Meta did not certify, and never
 * holds a request. What cannot go out lands in the owner's bell: the hour
 * stands and she was told it would be cancelled.
 */
class TellClosureBooking implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Room to wait out the gap: being released is a try, an exception is not retried. */
    public int $tries = 30;

    public int $maxExceptions = 1;

    public int $uniqueFor = 3600;

    /**
     * @param  list<int>  $appointmentIds  The same person's bookings inside the closure.
     */
    public function __construct(public int $businessId, public array $appointmentIds, public ?string $reason = null) {}

    public function uniqueId(): string
    {
        return $this->businessId.':'.implode(',', $this->appointmentIds);
    }

    public function handle(Tenant $tenant, EvolutionApi $evolution, CancelAppointment $cancel): void
    {
        $tenant->for($this->businessId, function () use ($evolution, $cancel): void {
            $business = Business::query()->find($this->businessId);
            $bookings = Appointment::query()->with(['customer', 'business'])
                ->whereIn('id', $this->appointmentIds)
                ->where('status', AppointmentStatus::Confirmed)
                ->orderBy('starts_at')
                ->get();

            // Cancelled, attended or moved since, or already told: nobody is waiting on this notice any more.
            if ($business === null || $bookings->isEmpty() || Cache::has($this->toldKey())) {
                return;
            }

            $first = $bookings->first();
            $customer = $first->customer;
            $phone = preg_replace('/\D+/', '', (string) $customer?->phone) ?? '';
            $gate = ProactiveSends::for($business);

            // Read again: the line may have dropped, the person been blocked, or reminders spent the day's cap.
            if (! $business->canMessageCustomers() || ! $business->hasOwnWhatsAppLine() || strlen($phone) < 8
                || $customer->blocked_at !== null || $gate->refusal((string) $customer->phone, $customer) !== null) {
                $this->held($bookings);

                return;
            }

            if (($wait = $gate->secondsUntilNext()) > 0) {
                $this->release($wait);

                return;
            }

            $text = $business->speaking(fn (): string => (new AppointmentCancelledByClosure($first, $this->reason, $bookings->slice(1)->values()->all()))->text());

            $sent = rescue(function () use ($evolution, $business, $phone, $text): bool {
                $evolution->sendText((string) $business->whatsapp_instance, $phone, $text, EvolutionApi::typingFor($text));

                return true;
            }, false);

            // A failed send leaves the hours standing, and she hears it: the customer is not left at a closed door.
            if (! $sent) {
                $this->held($bookings);

                return;
            }

            // Marked first: whatever fails after this point must never send the message again.
            Cache::put($this->toldKey(), true, now()->addDay());
            $gate->sent((string) $customer->phone);
            $bookings->each(fn (Appointment $booking) => $cancel->handle($booking));

            // In the thread too: a reply to the notice needs the assistant to know why.
            Conversation::query()->where('contact_phone', $customer->phone)->first()?->messages()->create([
                'direction' => MessageDirection::Out,
                'author' => MessageAuthor::Assistant,
                'body' => $text,
            ]);
        });
    }

    /**
     * @param  Collection<int, Appointment>  $bookings
     */
    private function held(Collection $bookings): void
    {
        $bookings->each(fn (Appointment $booking): bool => (new Panel($booking, [], ClosureNoticeHeld::class))->send());
    }

    private function toldKey(): string
    {
        return 'closure-told:'.$this->businessId.':'.implode(',', $this->appointmentIds);
    }
}
