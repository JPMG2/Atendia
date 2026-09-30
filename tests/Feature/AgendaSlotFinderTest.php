<?php

declare(strict_types=1);

use App\Actions\Agenda\BookAppointment;
use App\Actions\Agenda\CancelAppointment;
use App\Actions\Agenda\RescheduleAppointment;
use App\Classes\Main\Agenda;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/** A business open 09:00-12:00 every weekday, with the agenda on. */
function agendaBusiness(array $attributes = []): Business
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

    return $business;
}

/** Monday of the coming week, 00:00 local: every case walks from a known day. */
function nextMonday(Business $business): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek();
}

test('a day of open hours becomes slots as long as the service lasts', function (): void {
    $business = agendaBusiness();
    $service = $business->services()->create(['name' => 'Ecodoppler', 'duration_minutes' => 60, 'is_bookable' => true]);

    $slots = app(SlotFinder::class)->freeSlots($business, nextMonday($business), $service);

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->format('H:i'), $slots))
        ->toBe(['09:00', '10:00', '11:00']);
});

test('the default slot length covers a booking without a service', function (): void {
    $business = agendaBusiness();

    $slots = app(SlotFinder::class)->freeSlots($business, nextMonday($business));

    expect($slots)->toHaveCount(6);
});

test('a closed day has no slots', function (): void {
    $business = agendaBusiness();

    expect(app(SlotFinder::class)->freeSlots($business, nextMonday($business)->addDays(6)))->toBe([]);
});

test('the agenda turned off offers nothing', function (): void {
    $business = agendaBusiness(['appointments_enabled' => false]);

    expect(app(SlotFinder::class)->freeSlots($business, nextMonday($business)))->toBe([]);
});

test('a booked hour stops being offered while capacity is one', function (): void {
    $business = agendaBusiness();
    $monday = nextMonday($business);

    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id])->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    $slots = array_map(fn (CarbonImmutable $slot): string => $slot->format('H:i'), app(SlotFinder::class)->freeSlots($business, $monday));

    expect($slots)->not->toContain('09:00')
        ->and($slots)->toContain('09:30');
});

test('capacity for two keeps the hour open until both chairs are taken', function (): void {
    $business = agendaBusiness(['appointment_capacity' => 2]);
    $monday = nextMonday($business);
    $customer = Customer::factory()->create(['business_id' => $business->id]);

    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    expect(app(SlotFinder::class)->freeSlots($business, $monday))
        ->toContainEqual($monday->setTime(9, 0));

    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    expect(app(SlotFinder::class)->freeSlots($business, $monday))
        ->not->toContainEqual($monday->setTime(9, 0));
});

test('a cancelled booking frees its hour again', function (): void {
    $business = agendaBusiness();
    $monday = nextMonday($business);

    $appointment = Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id])->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    app(CancelAppointment::class)->handle($appointment);

    expect(app(SlotFinder::class)->freeSlots($business, $monday))
        ->toContainEqual($monday->setTime(9, 0));
});

test('the daily cap closes the day once it is reached', function (): void {
    $business = agendaBusiness(['appointments_per_day' => 1]);
    $monday = nextMonday($business);

    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id])->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    expect(app(SlotFinder::class)->freeSlots($business, $monday))->toBe([]);
});

test('hours already gone today are not offered', function (): void {
    $business = agendaBusiness();
    $timezone = $business->localTimezone();
    // A Monday at 10:15 local: the 09:00 and 09:30 slots are past.
    $now = CarbonImmutable::now($timezone)->addWeek()->startOfWeek()->setTime(10, 15);

    $slots = app(SlotFinder::class)->freeSlots($business, $now, null, $now);

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->format('H:i'), $slots))
        ->toBe(['10:30', '11:00', '11:30']);
});

test('the next free slots walk forward across closed days', function (): void {
    $business = agendaBusiness();
    // Saturday 00:00 local: the agenda only opens again on Monday.
    $saturday = nextMonday($business)->addDays(5);

    $slots = app(SlotFinder::class)->nextSlots($business, null, $saturday, 2);

    expect($slots)->toHaveCount(2)
        ->and($slots[0]->format('D H:i'))->toBe('Mon 09:00');
});

test('booking takes the hour and refuses the second taker', function (): void {
    $business = agendaBusiness();
    $customer = Customer::factory()->create(['business_id' => $business->id]);
    $service = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $monday = nextMonday($business)->setTime(9, 0);

    $appointment = app(BookAppointment::class)->handle($business, $customer, $monday, $service);

    expect($appointment->ends_at->setTimezone($business->localTimezone())->format('H:i'))->toBe('09:30');

    app(BookAppointment::class)->handle($business, $customer, $monday, $service);
})->throws(RuntimeException::class);

test('a booker who arrives while the same hour is being written is refused', function (): void {
    $business = agendaBusiness();
    $customer = Customer::factory()->create(['business_id' => $business->id]);
    $monday = nextMonday($business)->setTime(9, 0);

    // Standing in for the other booker mid-write: the hour is still free in the
    // table, so only the lock can keep this one out.
    $held = Cache::lock('agenda:'.$business->id.':'.$monday->utc()->format('YmdHi'), 10);
    $held->get();

    expect(fn () => app(BookAppointment::class)->handle($business, $customer, $monday))
        ->toThrow(RuntimeException::class);

    $held->release();

    expect(app(BookAppointment::class)->handle($business, $customer, $monday))->toBeInstanceOf(Appointment::class);
});

test('a booking moves to a free hour and keeps its own out of the way', function (): void {
    $business = agendaBusiness();
    $customer = Customer::factory()->create(['business_id' => $business->id]);
    $monday = nextMonday($business);

    $appointment = app(BookAppointment::class)->handle($business, $customer, $monday->setTime(9, 0));
    app(RescheduleAppointment::class)->handle($appointment, $monday->setTime(11, 0));

    expect($appointment->fresh()->starts_at->setTimezone($business->localTimezone())->format('H:i'))->toBe('11:00');
});

test('a service of another business can never be booked', function (): void {
    $business = agendaBusiness();
    $theirs = agendaBusiness();
    $service = $theirs->services()->create(['name' => 'Ajeno', 'duration_minutes' => 30, 'is_bookable' => true]);

    expect(fn () => app(Agenda::class, ['business' => $business])
        ->freeSlots(nextMonday($business), $service->id))
        ->toThrow(ModelNotFoundException::class);
});
