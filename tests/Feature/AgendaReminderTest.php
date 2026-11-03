<?php

declare(strict_types=1);

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\MessageDirection;
use App\Enums\PanelNotificationType;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PanelNotification;
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

test('a number typed in the booking link is not written to until that person writes', function (): void {
    $business = remindingBusiness();
    // Anyone can type someone else's number in the link: the business line must not write first.
    $stranger = Customer::factory()->create(['business_id' => $business->id]);
    $booking = bookingIn($business, 20, ['customer_id' => $stranger->id, 'source' => AppointmentSource::Link]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    Http::assertNothingSent();

    // The confirmation screen's button: the person sends the ready message from their own phone.
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => $stranger->phone]);
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'direction' => MessageDirection::In]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSentCount(1);
    expect($booking->fresh()->reminder_sent_at)->not->toBeNull();
});

test('a reminder held for a person who never wrote tells the owner once, whatever the ticks', function (): void {
    $business = remindingBusiness();
    $stranger = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Lucía']);
    bookingIn($business, 20, ['customer_id' => $stranger->id, 'source' => AppointmentSource::Link]);

    // Right after booking the person may still write from the confirmation screen: no row yet.
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    expect(PanelNotification::query()->where('business_id', $business->id)->count())->toBe(0);

    $this->travel(61)->minutes();
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    $notice = PanelNotification::query()->where('business_id', $business->id)->sole();

    expect($notice->type)->toBe(PanelNotificationType::ReminderHeld)
        ->and($notice->payload['why'])->toBe('cold')
        ->and(__('bell.lines.reminder_held', $notice->payload))->toContain('Lucía')->toContain('todavía no le escribió');
});

test('the held row is taken back once the reminder goes after all', function (): void {
    $business = remindingBusiness();
    $stranger = Customer::factory()->create(['business_id' => $business->id]);
    $booking = bookingIn($business, 2, ['customer_id' => $stranger->id, 'source' => AppointmentSource::Link]);

    // Two hours from the turn the owner hears it at once.
    $this->artisan('atendia:appointment-reminders')->assertSuccessful();
    expect(PanelNotification::query()->where('business_id', $business->id)->count())->toBe(1);

    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => $stranger->phone]);
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'direction' => MessageDirection::In]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    expect($booking->fresh()->reminder_sent_at)->not->toBeNull()
        ->and(PanelNotification::query()->where('business_id', $business->id)->count())->toBe(0);
});

test('a reminder held only by the day cap tells the owner nothing: it leaves at the next tick', function (): void {
    config(['atendia.outbound.daily_cap' => 0, 'atendia.outbound.warmup' => []]);
    $business = remindingBusiness();
    bookingIn($business, 20);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    expect(PanelNotification::query()->where('business_id', $business->id)->count())->toBe(0);
});

test('a booking typed the local way is the same person who then writes from WhatsApp', function (): void {
    $business = remindingBusiness(['country_id' => Country::factory()->create(['phone_code' => '+58'])->id]);

    // The public link stores what the visitor typed, the local way.
    $customer = $business->customerWithPhone('0412-555 1234', 'Pedro');
    expect($customer->phone)->toBe('584125551234');

    // WhatsApp sends the international digits: the same thread, so the reminder goes.
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => '584125551234']);
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'direction' => MessageDirection::In]);
    $booking = bookingIn($business, 20, ['customer_id' => $customer->id, 'source' => AppointmentSource::Link]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    expect($booking->fresh()->reminder_sent_at)->not->toBeNull()
        ->and($business->customerWithPhone('+58 0412 5551234', 'Pedro')->id)->toBe($customer->id)
        ->and($business->customerWithPhone('0058 412 5551234', 'Pedro')->id)->toBe($customer->id);
});
