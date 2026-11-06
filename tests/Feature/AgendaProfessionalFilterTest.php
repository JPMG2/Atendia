<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\ProfessionalClosure;
use App\Models\ProfessionalHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Whose agenda is on screen
|--------------------------------------------------------------------------
| Everybody's by default; one person's when the owner picks them: their
| bookings, their free hours, and their time away on the week.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->setLocale('es');
});

/** An owner open 09:00-12:00 on weekdays, the agenda on, two professionals and a booking of each plus an old one. */
function filterOwner(): array
{
    $business = Business::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $monday = CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek();
    $make = fn (string $name, ?int $professional, int $hour) => Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => $name])->id,
        'professional_id' => $professional,
        'starts_at' => $monday->setTime($hour, 0)->utc(),
        'ends_at' => $monday->setTime($hour, 30)->utc(),
    ]);
    $make('Cliente de Ana', $ana->id, 9);
    $make('Cliente de Luis', $luis->id, 10);
    $make('Cliente Antiguo', null, 11);

    $owner = User::factory()->create(['business_id' => $business->id])->refresh();
    test()->actingAs($owner);

    return [$owner, $ana, $luis, $monday];
}

test('the filter is not drawn while nobody is set up', function (): void {
    $business = Business::factory()->create(['appointments_enabled' => true]);
    test()->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    livewire('agenda.index')->assertDontSee(__('agenda.filter.professional'));
});

test('with people set up it offers everybody or one person, everybody first', function (): void {
    filterOwner();

    livewire('agenda.index')->assertSee(__('agenda.filter.professional'))->assertSee(__('agenda.filter.all'))->assertSet('professional', null);
});

test('everybody\'s day shows every booking, one person\'s only theirs', function (): void {
    [, $ana, $luis, $monday] = filterOwner();
    $page = livewire('agenda.index')->set('day', $monday->format('Y-m-d'));

    $page->assertSee('Cliente de Ana')->assertSee('Cliente de Luis')->assertSee('Cliente Antiguo');

    $page->set('professional', $ana->id)
        ->assertSee('Cliente de Ana')->assertDontSee('Cliente de Luis')->assertDontSee('Cliente Antiguo');

    $page->set('professional', $luis->id)
        ->assertSee('Cliente de Luis')->assertDontSee('Cliente de Ana');

    $page->set('professional', null)->assertSee('Cliente de Ana')->assertSee('Cliente de Luis');
});

test('the free hours follow the person on screen', function (): void {
    [, $ana, , $monday] = filterOwner();
    ProfessionalHour::factory()->create(['business_id' => $ana->business_id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $page = livewire('agenda.index')->set('day', $monday->format('Y-m-d'));

    expect(array_keys($page->instance()->freeHours))->toContain('11:30');

    $page->set('professional', $ana->id);

    expect(array_keys($page->instance()->freeHours))->toBe(['09:30']);
});

test('the week of one person counts only their bookings and says when they are away', function (): void {
    [, $ana, , $monday] = filterOwner();
    ProfessionalClosure::factory()->create(['business_id' => $ana->business_id, 'professional_id' => $ana->id, 'starts_on' => $monday->addDay()->format('Y-m-d'), 'ends_on' => $monday->addDay()->format('Y-m-d')]);

    $week = livewire('agenda.index')->set('day', $monday->format('Y-m-d'))->call('showWeek')->set('professional', $ana->id)->assertSee(__('agenda.week_away'));

    expect(collect($week->instance()->weekDays)->sum(fn (array $slot): int => $slot['bookings']->count()))->toBe(1);
});

test('booking while looking at one person starts with them picked', function (): void {
    [, , $luis, $monday] = filterOwner();

    livewire('agenda.index')->set('day', $monday->format('Y-m-d'))->set('professional', $luis->id)->call('book')
        ->assertSet('form.professional_id', $luis->id);
});
