<?php

declare(strict_types=1);

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** An owner of a business open 09:00-12:00 on weekdays, with the agenda on. */
function agendaOwner(array $attributes = []): User
{
    $business = Business::factory()->create([
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
        ...$attributes,
    ]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    return User::factory()->create(['business_id' => $business->id])->refresh();
}

function screenMonday(Business $business): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek();
}

test('the agenda paints the day with its bookings and its free hours', function (): void {
    $owner = agendaOwner();
    $monday = screenMonday($owner->business);
    $customer = Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana Gómez']);

    Appointment::factory()->create([
        'business_id' => $owner->business_id,
        'customer_id' => $customer->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    $this->actingAs($owner);

    livewire('agenda.index')
        ->set('day', $monday->format('Y-m-d'))
        ->assertSee('Ana Gómez')
        ->assertSee('09:00')
        ->assertSee('09:30');
});

test('the owner books a customer from a free hour', function (): void {
    $owner = agendaOwner();
    $monday = screenMonday($owner->business);
    $customer = Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana Gómez']);
    $this->actingAs($owner);

    livewire('agenda.index')
        ->set('day', $monday->format('Y-m-d'))
        ->call('book', '10:00')
        ->set('form.customer_id', $customer->id)
        ->set('form.notes', 'Primera vez')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('sheetOpen', false);

    $booking = Appointment::query()->sole();

    expect($booking->starts_at->setTimezone($owner->business->localTimezone())->format('H:i'))->toBe('10:00')
        ->and($booking->source)->toBe(AppointmentSource::Owner)
        ->and($booking->notes)->toBe('Primera vez');
});

test('a booking without a customer is refused', function (): void {
    $owner = agendaOwner();
    $this->actingAs($owner);

    livewire('agenda.index')
        ->call('book', '10:00')
        ->call('save')
        ->assertHasErrors(['customer_id' => 'required']);

    expect(Appointment::query()->count())->toBe(0);
});

test('a customer of another business can never be booked', function (): void {
    $owner = agendaOwner();
    $theirs = Customer::factory()->create(['business_id' => Business::factory()->create()->id]);
    $this->actingAs($owner);

    livewire('agenda.index')
        ->call('book', '10:00')
        ->set('form.customer_id', $theirs->id)
        ->call('save')
        ->assertHasErrors('customer_id');
});

test('the owner moves a booking to another free hour and cancels another', function (): void {
    $owner = agendaOwner();
    $monday = screenMonday($owner->business);
    $customer = Customer::factory()->create(['business_id' => $owner->business_id]);
    $booking = Appointment::factory()->create([
        'business_id' => $owner->business_id,
        'customer_id' => $customer->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);
    $this->actingAs($owner);

    $screen = livewire('agenda.index')
        ->set('day', $monday->format('Y-m-d'))
        ->call('move', $booking->id)
        ->set('form.time', '11:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($booking->fresh()->starts_at->setTimezone($owner->business->localTimezone())->format('H:i'))->toBe('11:00');

    $screen->call('cancel', $booking->id);

    expect($booking->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

test('a booking of another business is out of reach from the screen', function (): void {
    $owner = agendaOwner();
    $theirBusiness = Business::factory()->create();
    $theirs = Appointment::factory()->create([
        'business_id' => $theirBusiness->id,
        'customer_id' => Customer::factory()->create(['business_id' => $theirBusiness->id])->id,
    ]);
    $this->actingAs($owner);

    livewire('agenda.index')->call('cancel', $theirs->id)->assertStatus(404);

    expect($theirs->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

test('the agenda item hides from the menu until the business turns bookings on', function (): void {
    $this->seed(MenuSeeder::class);
    $owner = agendaOwner(['appointments_enabled' => false]);
    $this->actingAs($owner);

    livewire('navigation')->assertDontSee('Agenda');

    $owner->business->update(['appointments_enabled' => true]);

    livewire('navigation')->assertSee('Agenda');
});

test('the bookings card of Mi negocio turns the agenda on and off', function (): void {
    $owner = agendaOwner(['appointments_enabled' => false]);
    $this->actingAs($owner);

    livewire('client.section-appointments')
        ->set('form.appointments_enabled', true)
        ->set('form.appointment_capacity', 3)
        ->set('form.appointments_per_day', 12)
        ->set('form.appointment_slot_minutes', 45)
        ->call('save')
        ->assertHasNoErrors();

    $business = $owner->business->fresh();

    expect($business->appointments_enabled)->toBeTrue()
        ->and($business->appointment_capacity)->toBe(3)
        ->and($business->appointments_per_day)->toBe(12)
        ->and($business->appointment_slot_minutes)->toBe(45);
});

test('a capacity below one is refused', function (): void {
    $owner = agendaOwner();
    $this->actingAs($owner);

    livewire('client.section-appointments')
        ->set('form.appointment_capacity', 0)
        ->call('save')
        ->assertHasErrors(['appointment_capacity' => 'min']);
});

test('with the bookings off the agenda names the switch instead of blaming the day', function (): void {
    $owner = agendaOwner(['appointments_enabled' => false]);
    $this->actingAs($owner);

    livewire('agenda.index')
        ->assertSee(__('agenda.off.title'))
        ->assertSee(route('my-business.turnos'))
        // The two lines that lied: neither the day is full nor can anyone book.
        ->assertDontSee(__('agenda.free.none'))
        ->assertDontSee(__('agenda.empty.body'))
        ->assertDontSee(__('agenda.book'));
});

test('a business yet to be born opens the agenda instead of erroring', function (): void {
    $this->actingAs(User::factory()->create(['business_id' => null])->refresh());

    livewire('agenda.index')->assertOk()->assertSee(__('agenda.off.title'));
});

test('an agent never reaches the agenda', function (): void {
    $owner = agendaOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles(['agent']);

    $this->actingAs($agent->refresh())->get(route('agenda'))->assertForbidden();
});
