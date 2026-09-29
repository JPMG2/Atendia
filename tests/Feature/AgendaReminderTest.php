<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Every outbound call is faked: the bridge's host comes from the env.
    Http::fake(['*' => Http::response(['key' => ['id' => 'WA-1']])]);
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

function bookingIn(Business $business, int $hoursAhead, array $attributes = []): Appointment
{
    $startsAt = CarbonImmutable::now($business->localTimezone())->addHours($hoursAhead);

    return Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana'])->id,
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
