<?php

declare(strict_types=1);

use App\Actions\Agenda\BookAppointment;
use App\Actions\Agenda\RescheduleAppointment;
use App\Classes\Main\Client;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalClosure;
use App\Models\User;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Time away of one professional
|--------------------------------------------------------------------------
| The business opens and they do not: their agenda is shut for those days and
| nobody else's. Bookings already standing inside it are never cancelled
| behind anyone's back: the closure is refused until they are moved.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->setLocale('es');
});

/** An owner open 09:00-12:00 on weekdays with the agenda on, and two professionals. */
function awayOwner(): array
{
    $business = Business::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $owner = User::factory()->create(['business_id' => $business->id])->refresh();
    test()->actingAs($owner);

    return [$owner, $ana, $luis];
}

function awayMonday(Business $business, int $plusWeeks = 1): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeeks($plusWeeks)->startOfWeek();
}

function awayClosure(Professional $professional, CarbonImmutable $from, CarbonImmutable $to): ProfessionalClosure
{
    return ProfessionalClosure::factory()->create([
        'business_id' => $professional->business_id, 'professional_id' => $professional->id,
        'starts_on' => $from->format('Y-m-d'), 'ends_on' => $to->format('Y-m-d'),
    ]);
}

test('a professional away offers nothing those days while the business and the others keep going', function (): void {
    [$owner, $ana, $luis] = awayOwner();
    $monday = awayMonday($owner->business);
    awayClosure($ana, $monday, $monday->addDays(2));
    $finder = app(SlotFinder::class);

    expect($finder->freeSlots($owner->business, $monday, professional: $ana->fresh()))->toBe([])
        ->and($finder->freeSlots($owner->business, $monday->addDays(3), professional: $ana->fresh()))->toHaveCount(6)
        ->and($finder->freeSlots($owner->business, $monday, professional: $luis->fresh()))->toHaveCount(6)
        ->and($finder->freeSlots($owner->business, $monday))->toHaveCount(6);
});

test('with nobody named the hour goes to whoever is not away', function (): void {
    [$owner, $ana, $luis] = awayOwner();
    $monday = awayMonday($owner->business);
    awayClosure($ana, $monday, $monday);
    $customer = Customer::factory()->create(['business_id' => $owner->business_id]);

    $booking = app(BookAppointment::class)->handle($owner->business, $customer, $monday->setTime(9, 0));

    expect($booking->professional_id)->toBe($luis->id);
});

test('a booking cannot be moved into the days its professional is away', function (): void {
    [$owner, $ana] = awayOwner();
    $monday = awayMonday($owner->business);
    awayClosure($ana, $monday->addDay(), $monday->addDay());
    $booking = Appointment::factory()->create([
        'business_id' => $owner->business_id, 'customer_id' => Customer::factory()->create(['business_id' => $owner->business_id])->id,
        'professional_id' => $ana->id, 'starts_at' => $monday->setTime(9, 0)->utc(), 'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    expect(fn () => app(RescheduleAppointment::class)->handle($booking->fresh(), $monday->addDay()->setTime(9, 0)))
        ->toThrow(RuntimeException::class);
});

test('the owner loads time away from the sheet, sees it listed and removes it', function (): void {
    [$owner, $ana] = awayOwner();
    $monday = awayMonday($owner->business);
    $range = $monday->format('Y-m-d').'..'.$monday->addDays(4)->format('Y-m-d');

    $sheet = livewire('professionals.index')
        ->call('open', $ana->id)
        ->set('professional.closure_range', $range)
        ->set('professional.closure_reason', 'Vacaciones')
        ->call('addClosure')
        ->assertHasNoErrors()
        ->assertDispatched('notify', type: 'success')
        ->assertSet('professional.closure_range', '')
        ->assertSee('Vacaciones');

    $closure = ProfessionalClosure::query()->sole();

    expect($closure->professional_id)->toBe($ana->id)
        ->and($closure->business_id)->toBe($owner->business_id)
        ->and($closure->starts_on->format('Y-m-d'))->toBe($monday->format('Y-m-d'));

    $sheet->call('removeClosure', $closure->id)->assertDispatched('notify', type: 'success');

    expect(ProfessionalClosure::count())->toBe(0);
});

test('a single day is a range of one, and days already loaded are not loaded twice', function (): void {
    [$owner, $ana] = awayOwner();
    $monday = awayMonday($owner->business)->format('Y-m-d');

    $sheet = livewire('professionals.index')->call('open', $ana->id)
        ->set('professional.closure_range', $monday)->call('addClosure')->assertDispatched('notify', type: 'success');
    $sheet->set('professional.closure_range', $monday)->call('addClosure')->assertDispatched('notify', type: 'info');

    expect(ProfessionalClosure::count())->toBe(1);
});

test('bookings standing inside the days are counted and the closure is refused, nothing cancelled', function (): void {
    [$owner, $ana] = awayOwner();
    $monday = awayMonday($owner->business);
    $customer = Customer::factory()->create(['business_id' => $owner->business_id]);

    foreach ([9, 10] as $hour) {
        Appointment::factory()->create(['business_id' => $owner->business_id, 'customer_id' => $customer->id, 'professional_id' => $ana->id, 'starts_at' => $monday->setTime($hour, 0)->utc(), 'ends_at' => $monday->setTime($hour, 30)->utc()]);
    }

    livewire('professionals.index')->call('open', $ana->id)
        ->set('professional.closure_range', $monday->format('Y-m-d'))
        ->call('addClosure')
        ->assertDispatched('notify', type: 'warning');

    expect(ProfessionalClosure::count())->toBe(0)
        ->and(Appointment::query()->where('status', 'confirmed')->count())->toBe(2)
        ->and(trans_choice('professionals.closures.booked', 2, ['count' => 2]))->toContain('2 turnos');
});

test('a range that is not real is refused by the sheet', function (): void {
    [$owner, $ana] = awayOwner();

    livewire('professionals.index')->call('open', $ana->id)
        ->set('professional.closure_range', 'mañana')
        ->call('addClosure')
        ->assertHasErrors(['range']);

    expect(ProfessionalClosure::count())->toBe(0);
});

test('a new professional has nowhere to load time away until saved', function (): void {
    awayOwner();

    livewire('professionals.index')->call('open')->assertSee(__('professionals.closures.save_first'));
});

test('time away of a professional of another business can be neither read, loaded nor removed from here', function (): void {
    [$owner] = awayOwner();
    $stranger = Professional::factory()->create();
    $closure = ProfessionalClosure::factory()->create(['business_id' => $stranger->business_id, 'professional_id' => $stranger->id]);
    $piece = Client::for($owner)->professionals;

    expect(fn () => $piece->closuresOf($stranger->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $piece->addClosure($stranger->id, '2030-01-01', null))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $piece->removeClosure($stranger->id, $closure->id))->toThrow(ModelNotFoundException::class)
        ->and($closure->fresh())->not->toBeNull();
});

test('a business only ever sees its own professionals\' time away', function (): void {
    [$owner, $ana] = awayOwner();
    awayClosure($ana, awayMonday($owner->business), awayMonday($owner->business));
    ProfessionalClosure::factory()->create();

    expect(ProfessionalClosure::count())->toBe(1);
});
