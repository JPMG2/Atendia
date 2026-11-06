<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Who attends, in the booking sheet of the Agenda
|--------------------------------------------------------------------------
| The picker follows the service; empty means anyone free; the hours follow
| the person; and a booking says with whom it is.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->setLocale('es');
});

/** An owner of a business open 09:00-12:00 on weekdays with the agenda on, and one customer. */
function pickerOwner(): User
{
    $business = Business::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Marta Gil']);
    $owner = User::factory()->create(['business_id' => $business->id])->refresh();
    test()->actingAs($owner);

    return $owner;
}

function pickerMonday(Business $business): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek();
}

function pickerService(Business $business, string $name): Service
{
    return $business->services()->create(['name' => $name, 'duration_minutes' => 30, 'is_bookable' => true]);
}

test('a business with nobody set up sees no picker and books as always', function (): void {
    $owner = pickerOwner();

    livewire('agenda.index')
        ->set('day', pickerMonday($owner->business)->format('Y-m-d'))
        ->call('book')
        ->assertDontSee(__('agenda.form.professional'));
});

test('the picker lists who attends the service chosen and offers anyone free when left empty', function (): void {
    $owner = pickerOwner();
    $cut = pickerService($owner->business, 'Corte');
    $color = pickerService($owner->business, 'Color');
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $owner->business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $ana->services()->attach([$cut->id, $color->id]);
    $luis->services()->attach($cut->id);
    $monday = pickerMonday($owner->business);

    $sheet = livewire('agenda.index')->set('day', $monday->format('Y-m-d'))->call('book')->set('form.service_id', $cut->id);

    expect($sheet->instance()->professionalOptions($cut->id))->toBe([$ana->id => 'Ana Soto', $luis->id => 'Luis Paz'])
        ->and($sheet->instance()->professionalOptions($color->id))->toBe([$ana->id => 'Ana Soto']);

    $sheet->assertSee(__('agenda.form.professional'))
        ->assertSee(__('agenda.form.professional_placeholder'));
});

test('booking with a chosen professional stores them, and with none the first free one takes it', function (): void {
    $owner = pickerOwner();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $owner->business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $customer = $owner->business->customers()->sole();
    $monday = pickerMonday($owner->business)->format('Y-m-d');

    livewire('agenda.index')->set('day', $monday)->call('book')
        ->set('form.customer_id', $customer->id)->set('form.professional_id', $luis->id)->set('form.time', '09:00')
        ->call('save')->assertHasNoErrors()->assertDispatched('notify', type: 'success');

    livewire('agenda.index')->set('day', $monday)->call('book')
        ->set('form.customer_id', $customer->id)->set('form.time', '09:00')
        ->call('save')->assertHasNoErrors()->assertDispatched('notify', type: 'success');

    expect(Appointment::query()->orderBy('id')->pluck('professional_id')->all())->toBe([$luis->id, $ana->id]);
});

test('the hours offered follow the professional picked', function (): void {
    $owner = pickerOwner();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $luis = $owner->business->professionals()->create(['name' => 'Luis Paz']);
    ProfessionalHour::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $monday = pickerMonday($owner->business)->format('Y-m-d');

    $component = livewire('agenda.index')->set('day', $monday)->call('book')->instance();

    expect(array_keys($component->hoursFor($monday, null, null, $ana->id)))->toBe(['09:00', '09:30'])
        ->and($component->hoursFor($monday, null, null, $luis->id))->toHaveCount(6)
        ->and($component->hoursFor($monday))->toHaveCount(6);
});

test('changing the service drops a professional who does not attend the new one', function (): void {
    $owner = pickerOwner();
    $cut = pickerService($owner->business, 'Corte');
    $color = pickerService($owner->business, 'Color');
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut->id);

    livewire('agenda.index')->set('day', pickerMonday($owner->business)->format('Y-m-d'))->call('book')
        ->set('form.service_id', $cut->id)
        ->set('form.professional_id', $ana->id)
        ->assertSet('form.professional_id', $ana->id)
        ->set('form.service_id', $color->id)
        ->assertSet('form.professional_id', null);
});

test('a professional who does not attend the service cannot be forced through the sheet', function (): void {
    $owner = pickerOwner();
    $cut = pickerService($owner->business, 'Corte');
    $color = pickerService($owner->business, 'Color');
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut->id);

    livewire('agenda.index')->set('day', pickerMonday($owner->business)->format('Y-m-d'))->call('book')
        ->set('form.customer_id', $owner->business->customers()->sole()->id)
        ->set('form.service_id', $color->id)
        ->set('form.professional_id', $ana->id)
        ->set('form.time', '09:00')
        ->call('save')
        ->assertDispatched('notify', type: 'error');

    expect(Appointment::count())->toBe(0);
});

test('a switched-off professional is refused by the sheet', function (): void {
    $owner = pickerOwner();
    $off = $owner->business->professionals()->create(['name' => 'Luis Paz', 'is_active' => false]);

    livewire('agenda.index')->set('day', pickerMonday($owner->business)->format('Y-m-d'))->call('book')
        ->set('form.customer_id', $owner->business->customers()->sole()->id)
        ->set('form.professional_id', $off->id)
        ->set('form.time', '09:00')
        ->call('save')
        ->assertHasErrors(['professional_id']);

    expect(Appointment::count())->toBe(0);
});

test('a professional of another business is not found, whatever id arrives', function (): void {
    $owner = pickerOwner();
    $stranger = Professional::factory()->create();

    livewire('agenda.index')->set('day', pickerMonday($owner->business)->format('Y-m-d'))->call('book')
        ->set('form.professional_id', $stranger->id)
        ->assertStatus(404);

    expect(Appointment::count())->toBe(0);
});

test('the day says with whom each booking is, and says nothing for one from before', function (): void {
    $owner = pickerOwner();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $monday = pickerMonday($owner->business);
    Appointment::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id, 'starts_at' => $monday->setTime(9, 0)->utc(), 'ends_at' => $monday->setTime(9, 30)->utc()]);
    Appointment::factory()->create(['business_id' => $owner->business_id, 'starts_at' => $monday->setTime(10, 0)->utc(), 'ends_at' => $monday->setTime(10, 30)->utc()]);

    livewire('agenda.index')->set('day', $monday->format('Y-m-d'))
        ->assertSee('con Ana Soto')
        ->assertSeeInOrder(['con Ana Soto']);

    expect(substr_count(livewire('agenda.index')->set('day', $monday->format('Y-m-d'))->html(), 'con Ana Soto'))->toBe(1);
});

test('moving a booking says who has it, keeps them, and offers their hours', function (): void {
    $owner = pickerOwner();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    ProfessionalHour::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $monday = pickerMonday($owner->business);
    $booking = Appointment::factory()->create([
        'business_id' => $owner->business_id,
        'customer_id' => $owner->business->customers()->sole()->id,
        'professional_id' => $ana->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    livewire('agenda.index')->set('day', $monday->format('Y-m-d'))
        ->call('move', $booking->id)
        ->assertSee('con Ana Soto')
        ->set('form.time', '09:30')
        ->call('save')
        ->assertHasNoErrors();

    $moved = $booking->fresh();

    expect($moved->professional_id)->toBe($ana->id)
        ->and($moved->starts_at->setTimezone($owner->business->localTimezone())->format('H:i'))->toBe('09:30');
});
