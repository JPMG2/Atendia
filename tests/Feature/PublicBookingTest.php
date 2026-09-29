<?php

declare(strict_types=1);

use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/** A business open 09:00-20:00 every day, with the agenda on. */
function bookableBusiness(array $attributes = []): Business
{
    $business = Business::factory()->create([
        'name' => 'Consultorio Vida',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
        ...$attributes,
    ]);

    foreach (range(0, 6) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '20:00']);
    }

    return $business;
}

function tomorrowAt(Business $business, string $time): array
{
    $day = CarbonImmutable::now($business->localTimezone())->addDay();

    return [$day->format('Y-m-d'), $time];
}

test('every business is born with a booking link of its own', function (): void {
    $business = bookableBusiness();
    $other = bookableBusiness();

    expect($business->booking_code)->not->toBeNull()
        ->and($business->booking_code)->not->toBe($other->booking_code)
        ->and($business->bookingLink())->toContain($business->booking_code);
});

test('a customer books from the public link without any account', function (): void {
    $business = bookableBusiness();
    [$day, $time] = tomorrowAt($business, '10:00');

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', $time)
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', '+54 9 11 5555-0101')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSee(__('agenda.public.done_title'));

    $booking = Appointment::withoutGlobalScopes()->sole();

    expect($booking->business_id)->toBe($business->id)
        ->and($booking->source)->toBe(AppointmentSource::Link)
        ->and($booking->starts_at->setTimezone($business->localTimezone())->format('Y-m-d H:i'))->toBe("{$day} {$time}")
        ->and(Customer::withoutGlobalScopes()->sole()->name)->toBe('Ana Gómez');
});

test('a second booking from the same phone reuses the customer', function (): void {
    $business = bookableBusiness();
    [$day] = tomorrowAt($business, '10:00');
    $existing = Customer::factory()->create(['business_id' => $business->id, 'phone' => '5491155550101', 'name' => 'Ana G.']);

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', '11:00')
        ->set('form.name', 'Otra Ana')
        ->set('form.phone', '5491155550101')
        ->call('book')
        ->assertHasNoErrors();

    expect(Customer::withoutGlobalScopes()->count())->toBe(1)
        // A name already on file is the owner's: the link never overwrites it.
        ->and($existing->fresh()->name)->toBe('Ana G.')
        ->and(Appointment::withoutGlobalScopes()->sole()->customer_id)->toBe($existing->id);
});

test('the link refuses a booking without a name or a phone', function (): void {
    $business = bookableBusiness();
    [$day] = tomorrowAt($business, '10:00');

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', '10:00')
        ->call('book')
        ->assertHasErrors(['name', 'phone']);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

test('an hour taken in the meantime is answered, never double booked', function (): void {
    $business = bookableBusiness();
    [$day, $time] = tomorrowAt($business, '10:00');
    $screen = livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', $time)
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', '5491155550101');

    // Somebody else takes the hour between the page load and the tap.
    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id])->id,
        'starts_at' => CarbonImmutable::parse("{$day} {$time}", $business->localTimezone())->utc(),
        'ends_at' => CarbonImmutable::parse("{$day} {$time}", $business->localTimezone())->addMinutes(30)->utc(),
    ]);

    $screen->call('book')->assertSee(__('agenda.public.taken'));

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1);
});

test('the link is dead for a business without the agenda or suspended', function (): void {
    $off = bookableBusiness(['appointments_enabled' => false]);
    $suspended = bookableBusiness(['suspended_at' => now()]);

    $this->get(route('booking.public', ['code' => $off->booking_code]))->assertNotFound();
    $this->get(route('booking.public', ['code' => $suspended->booking_code]))->assertNotFound();
    $this->get(route('booking.public', ['code' => 'NOPE']))->assertNotFound();
});

test('the public page opens with no session at all', function (): void {
    $business = bookableBusiness();

    $this->get(route('booking.public', ['code' => $business->booking_code]))
        ->assertOk()
        ->assertSee($business->name)
        ->assertSee(__('agenda.public.title'));
});
