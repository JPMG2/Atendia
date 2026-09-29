<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Illuminate\Console\Command;

/**
 * The day-before nudge: every confirmed booking inside the reminder window
 * gets ONE message over the business's own WhatsApp, asking the customer to
 * confirm or ask for another hour. An empty chair is the agenda's real cost.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'atendia:appointment-reminders';

    protected $description = 'Remind each customer of their booking the day before, per business instance';

    public function handle(EvolutionApi $evolution): int
    {
        $businesses = Business::query()
            ->where('appointments_enabled', true)
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->get()
            ->filter(fn (Business $business): bool => $business->canMessageCustomers());

        foreach ($businesses as $business) {
            app(Tenant::class)->speakingAs($business, fn () => $this->remind($evolution, $business));
        }

        return self::SUCCESS;
    }

    private function remind(EvolutionApi $evolution, Business $business): void
    {
        $hours = (int) config('atendia.schedule.appointment_reminder_hours');
        $now = now($business->localTimezone());

        $due = $business->appointments()
            ->where('status', AppointmentStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', $now->utc())
            ->where('starts_at', '<=', $now->copy()->addHours($hours)->utc())
            ->with('customer', 'service')
            ->get();

        foreach ($due as $appointment) {
            // One failed send never cuts off the bookings after it.
            rescue(fn () => $this->send($evolution, $business, $appointment));
        }
    }

    private function send(EvolutionApi $evolution, Business $business, Appointment $appointment): void
    {
        $customer = $appointment->customer;

        if ($customer === null || $customer->blocked_at !== null) {
            return;
        }

        $when = $appointment->starts_at->setTimezone($business->localTimezone());
        $text = __('agenda.reminder', [
            'business' => $business->name,
            'day' => $when->locale(app()->getLocale())->translatedFormat('l j/n'),
            'time' => $when->format('H:i'),
            'what' => $appointment->service?->name ?? __('agenda.plain_slot'),
        ]);

        $evolution->sendText((string) $business->whatsapp_instance, $customer->phone, $text);

        // Stamped after the send: a failure leaves it due for the next tick.
        $appointment->update(['reminder_sent_at' => now()]);

        // In the thread too: a "confirmo" back needs the assistant to know why.
        Conversation::query()->where('contact_phone', $customer->phone)->first()?->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => $text,
        ]);

        $this->info("Appointment reminder sent for {$business->name} to {$customer->phone}");
    }
}
