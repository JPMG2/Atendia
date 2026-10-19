<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\MessageDirection;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Every outbound call is faked: the bridge's host comes from the env.
    Http::fake(['*' => Http::response(['key' => ['id' => 'WA-1']])]);

    // Wednesday noon in Buenos Aires: inside the customer send window.
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00', 'America/Argentina/Buenos_Aires'));
});

test('a reminder never leaves on Sunday or at night, and goes at the next open tick', function (): void {
    $business = remindingBusiness();
    $appointment = bookingIn($business, 20);

    $this->travelTo(CarbonImmutable::parse('2026-10-14 03:00', 'America/Argentina/Buenos_Aires'));
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    expect($appointment->fresh()->reminder_sent_at)->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00', 'America/Argentina/Buenos_Aires'));
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    expect($appointment->fresh()->reminder_sent_at)->toBeNull();
});

/** A connected business with the agenda on, open around the clock. */
function remindingBusiness(array $attributes = []): Business
{
    $business = Business::factory()->create([
        'name' => 'Consultorio Vida',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
        ...$attributes,
    ]);

    foreach (range(0, 6) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '00:00', 'closes_at' => '23:30']);
    }

    return $business;
}

/** A customer who wrote to the number once: the only kind a reminder may reach. */
function customerWhoWrote(Business $business, string $name = 'Ana'): Customer
{
    $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => $name]);
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => $customer->phone]);

    ConversationMessage::factory()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
        'direction' => MessageDirection::In,
    ]);

    return $customer;
}

function bookingIn(Business $business, int $hoursAhead, array $attributes = []): Appointment
{
    $startsAt = CarbonImmutable::now($business->localTimezone())->addHours($hoursAhead);

    return Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => ($attributes['customer_id'] ?? customerWhoWrote($business)->id),
        'starts_at' => $startsAt->utc(),
        'ends_at' => $startsAt->addMinutes(30)->utc(),
        ...$attributes,
    ]);
}

test('a booking inside the window is reminded once, whatever the ticks', function (): void {
    $business = remindingBusiness();
    $booking = bookingIn($business, 20);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSentCount(1);
    expect($booking->fresh()->reminder_sent_at)->not->toBeNull();
});

test('the reminder asks to confirm or to move it', function (): void {
    $business = remindingBusiness();
    bookingIn($business, 20);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $text = (string) ($request->data()['text'] ?? '');

        return str_contains($text, 'Consultorio Vida')
            && str_contains($text, 'Confirmo')
            && str_contains($text, 'Reprogramar');
    });
});

test('a booking further out than the window waits its turn', function (): void {
    $business = remindingBusiness();
    bookingIn($business, 48);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertNothingSent();
});

test('a cancelled booking is never reminded', function (): void {
    $business = remindingBusiness();
    bookingIn($business, 20, ['status' => AppointmentStatus::Cancelled]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertNothingSent();
});

test('a silenced business sends nothing to its customers', function (): void {
    $business = remindingBusiness(['suspended_at' => now()]);
    bookingIn($business, 20);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertNothingSent();
});

test('a business without the agenda is skipped', function (): void {
    $business = remindingBusiness(['appointments_enabled' => false]);
    bookingIn($business, 20);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertNothingSent();
});
