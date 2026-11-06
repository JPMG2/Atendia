<?php

declare(strict_types=1);

use App\Actions\Agenda\BookAppointment;
use App\Actions\Agenda\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalHour;
use App\Models\Service;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Open 09:00-12:00 on weekdays, 30-minute slots, one booking per hour: what every professional inherits. */
function proBusiness(array $attributes = []): Business
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

function proMonday(Business $business, int $plusDays = 0): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek()->addDays($plusDays);
}

/** @return list<string> */
function proHours(array $slots): array
{
    return array_map(fn (CarbonImmutable $slot): string => $slot->format('H:i'), $slots);
}

function proStaff(Business $business, string $name, int $order = 0, array $attributes = []): Professional
{
    return $business->professionals()->create(['name' => $name, 'sort_order' => $order, ...$attributes]);
}

/** A weekly shift of their own, 0 = Sunday. */
function proShift(Professional $professional, int $weekday, string $opens, string $closes): void
{
    ProfessionalHour::factory()->create([
        'business_id' => $professional->business_id,
        'professional_id' => $professional->id,
        'day_of_week' => $weekday,
        'opens_at' => $opens,
        'closes_at' => $closes,
    ]);
}

function proBook(Business $business, CarbonImmutable $startsAt, ?Service $service = null, ?Professional $professional = null): Appointment
{
    $customer = Customer::factory()->create(['business_id' => $business->id]);

    return app(BookAppointment::class)->handle($business, $customer, $startsAt, $service, professional: $professional);
}

test('a professional with shifts of their own offers only those, and one with none follows the business', function (): void {
    $business = proBusiness();
    $ana = proStaff($business, 'Ana');
    $luis = proStaff($business, 'Luis');
    proShift($ana, 1, '09:00', '10:00');

    $finder = app(SlotFinder::class);
    $monday = proMonday($business);

    expect(proHours($finder->freeSlots($business, $monday, professional: $ana->fresh())))->toBe(['09:00', '09:30'])
        ->and($finder->freeSlots($business, $monday->addDay(), professional: $ana->fresh()))->toBe([])
        ->and($finder->freeSlots($business, $monday, professional: $luis->fresh()))->toHaveCount(6)
        ->and($finder->freeSlots($business, $monday->addDay(), professional: $luis->fresh()))->toHaveCount(6);
});

test('a professional lasts as long as they say for a service, and the rest follow the service', function (): void {
    $business = proBusiness();
    $service = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 60, 'is_bookable' => true]);
    $ana = proStaff($business, 'Ana');
    $luis = proStaff($business, 'Luis');
    $ana->services()->attach($service->id, ['duration_minutes' => 30]);
    $luis->services()->attach($service->id);

    $finder = app(SlotFinder::class);
    $monday = proMonday($business);

    expect($finder->freeSlots($business, $monday, $service, professional: $ana->fresh()))->toHaveCount(6)
        ->and(proHours($finder->freeSlots($business, $monday, $service, professional: $luis->fresh())))->toBe(['09:00', '10:00', '11:00'])
        ->and($finder->slotMinutes($business, $service, $ana->fresh()))->toBe(30)
        ->and($finder->slotMinutes($business, $service, $luis->fresh()))->toBe(60)
        ->and($finder->slotMinutes($business, $service))->toBe(60);
});

test('a professional offers nothing for a service they do not attend, but a plain slot is open to them', function (): void {
    $business = proBusiness();
    $service = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $ana = proStaff($business, 'Ana');

    $finder = app(SlotFinder::class);

    expect($finder->freeSlots($business, proMonday($business), $service, professional: $ana->fresh()))->toBe([])
        ->and($finder->freeSlots($business, proMonday($business), professional: $ana->fresh()))->toHaveCount(6);
});

test('each professional has their own capacity and the business one is the default', function (): void {
    $business = proBusiness(['appointment_capacity' => 1]);
    $ana = proStaff($business, 'Ana', 1, ['appointment_capacity' => 2]);
    $luis = proStaff($business, 'Luis', 2);
    $nine = proMonday($business)->setTime(9, 0);

    proBook($business, $nine, professional: $ana);
    proBook($business, $nine, professional: $ana);
    proBook($business, $nine, professional: $luis);

    $finder = app(SlotFinder::class);

    expect($finder->isFree($business, $nine, null, null, $ana->fresh()))->toBeFalse()
        ->and($finder->isFree($business, $nine, null, null, $luis->fresh()))->toBeFalse()
        ->and(fn () => proBook($business, $nine, professional: $luis))->toThrow(RuntimeException::class);
});

test('the daily ceiling counts only that professional, and inherits the business one when empty', function (): void {
    $business = proBusiness(['appointments_per_day' => 2]);
    $ana = proStaff($business, 'Ana', 1, ['appointments_per_day' => 1]);
    $luis = proStaff($business, 'Luis', 2);
    $monday = proMonday($business);

    proBook($business, $monday->setTime(9, 0), professional: $ana);
    proBook($business, $monday->setTime(9, 0), professional: $luis);
    proBook($business, $monday->setTime(10, 0), professional: $luis);

    $finder = app(SlotFinder::class);

    expect($finder->freeSlots($business, $monday, professional: $ana->fresh()))->toBe([])
        ->and($finder->freeSlots($business, $monday, professional: $luis->fresh()))->toBe([])
        ->and($finder->freeSlots($business, $monday->addDay(), professional: $luis->fresh()))->toHaveCount(6);
});

test('without naming anyone the hour goes to the first free professional, in the owner order', function (): void {
    $business = proBusiness();
    $second = proStaff($business, 'Luis', 2);
    $first = proStaff($business, 'Ana', 1);
    $nine = proMonday($business)->setTime(9, 0);

    $one = proBook($business, $nine);
    $two = proBook($business, $nine);

    expect($one->professional_id)->toBe($first->id)
        ->and($two->professional_id)->toBe($second->id)
        ->and(fn () => proBook($business, $nine))->toThrow(RuntimeException::class);
});

test('anyone offers the hours that at least one professional can take, without repeats', function (): void {
    $business = proBusiness();
    $ana = proStaff($business, 'Ana', 1);
    proStaff($business, 'Luis', 2);
    proShift($ana, 1, '09:00', '10:00');

    expect(proHours(app(SlotFinder::class)->freeSlots($business, proMonday($business))))
        ->toBe(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30']);
});

test('a named professional keeps the booking and ends it by their own length', function (): void {
    $business = proBusiness();
    $service = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 60, 'is_bookable' => true]);
    $ana = proStaff($business, 'Ana');
    $ana->services()->attach($service->id, ['duration_minutes' => 45]);
    $nine = proMonday($business)->setTime(9, 0);

    $booking = proBook($business, $nine, $service, $ana);

    expect($booking->professional_id)->toBe($ana->id)
        ->and($booking->durationMinutes())->toBe(45)
        ->and($booking->status)->toBe(AppointmentStatus::Confirmed);
});

test('a switched-off professional takes no new bookings but their old one can still move', function (): void {
    $business = proBusiness();
    $ana = proStaff($business, 'Ana', 1);
    $luis = proStaff($business, 'Luis', 2);
    $monday = proMonday($business);
    $booking = proBook($business, $monday->setTime(9, 0), professional: $ana);

    $ana->update(['is_active' => false]);

    $finder = app(SlotFinder::class);

    expect($finder->freeSlots($business, $monday, professional: $ana->fresh()))->toBe([])
        ->and(proBook($business, $monday->setTime(9, 0))->professional_id)->toBe($luis->id);

    app(RescheduleAppointment::class)->handle($booking->fresh(), $monday->setTime(10, 0));

    expect($booking->fresh()->starts_at->setTimezone($business->localTimezone())->format('H:i'))->toBe('10:00')
        ->and($booking->fresh()->professional_id)->toBe($ana->id);
});

test('moving a booking keeps its professional and ends it by their length', function (): void {
    $business = proBusiness();
    $service = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 60, 'is_bookable' => true]);
    $ana = proStaff($business, 'Ana');
    $ana->services()->attach($service->id, ['duration_minutes' => 30]);
    $monday = proMonday($business);
    $booking = proBook($business, $monday->setTime(9, 0), $service, $ana);

    app(RescheduleAppointment::class)->handle($booking->fresh(), $monday->setTime(10, 0));

    $moved = $booking->fresh();

    expect($moved->professional_id)->toBe($ana->id)
        ->and($moved->starts_at->setTimezone($business->localTimezone())->format('H:i'))->toBe('10:00')
        ->and($moved->durationMinutes())->toBe(30);
});

test('a professional of another business never offers hours or takes a booking', function (): void {
    $business = proBusiness();
    $stranger = proStaff(proBusiness(), 'Ajeno');
    $nine = proMonday($business)->setTime(9, 0);

    expect(app(SlotFinder::class)->freeSlots($business, proMonday($business), professional: $stranger->fresh()))->toBe([])
        ->and(fn () => proBook($business, $nine, professional: $stranger))->toThrow(RuntimeException::class);
});

test('a day the business closes is closed for every professional', function (): void {
    $business = proBusiness();
    $ana = proStaff($business, 'Ana');
    $monday = proMonday($business);
    $business->closures()->create(['starts_on' => $monday->toDateString(), 'ends_on' => $monday->toDateString()]);

    expect(app(SlotFinder::class)->freeSlots($business->fresh(), $monday, professional: $ana->fresh()))->toBe([])
        ->and(app(SlotFinder::class)->freeSlots($business->fresh(), $monday))->toBe([]);
});

test('a service nobody attends keeps the single agenda of the business, unassigned', function (): void {
    $business = proBusiness();
    $attended = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $orphan = $business->services()->create(['name' => 'Consulta', 'duration_minutes' => 30, 'is_bookable' => true]);
    proStaff($business, 'Ana')->services()->attach($attended->id);

    $booking = proBook($business, proMonday($business)->setTime(9, 0), $orphan);

    expect($booking->professional_id)->toBeNull();
});

test('a booking from before professionals existed still takes its hour from every professional', function (): void {
    $business = proBusiness();
    $ana = proStaff($business, 'Ana', 1);
    $luis = proStaff($business, 'Luis', 2);
    $monday = proMonday($business);
    $nine = $monday->setTime(9, 0);
    Appointment::factory()->create(['business_id' => $business->id, 'starts_at' => $nine->utc(), 'ends_at' => $nine->addMinutes(30)->utc()]);

    $finder = app(SlotFinder::class);

    expect(proHours($finder->freeSlots($business, $monday, professional: $ana->fresh())))->not->toContain('09:00')
        ->and(proHours($finder->freeSlots($business, $monday, professional: $luis->fresh())))->not->toContain('09:00')
        ->and(proHours($finder->freeSlots($business, $monday)))->not->toContain('09:00')
        ->and(fn () => proBook($business, $nine))->toThrow(RuntimeException::class);
});

test('a booking of one professional does not take the hour from another', function (): void {
    $business = proBusiness();
    $ana = proStaff($business, 'Ana', 1);
    $luis = proStaff($business, 'Luis', 2);
    $monday = proMonday($business);
    proBook($business, $monday->setTime(9, 0), professional: $ana);

    $finder = app(SlotFinder::class);

    expect(proHours($finder->freeSlots($business, $monday, professional: $ana->fresh())))->not->toContain('09:00')
        ->and(proHours($finder->freeSlots($business, $monday, professional: $luis->fresh())))->toContain('09:00')
        ->and(Appointment::between($business, $monday, $monday->endOfDay(), $luis->id))->toHaveCount(0)
        ->and(Appointment::between($business, $monday, $monday->endOfDay(), $ana->id))->toHaveCount(1);
});
