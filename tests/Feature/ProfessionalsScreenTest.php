<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Menu;
use App\Models\Professional;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->setLocale('es');
});

/** The owner of a business with two services that take a booking, signed in. */
function proScreenOwner(): User
{
    $business = Business::factory()->create();
    $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $business->services()->create(['name' => 'Color', 'duration_minutes' => 90, 'is_bookable' => true]);

    $owner = User::factory()->create(['business_id' => $business->id])->refresh();
    test()->actingAs($owner);

    return $owner;
}

test('only the owner of a business reaches the screen', function (): void {
    $owner = proScreenOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles(['agent']);

    auth()->logout();
    $this->get(route('professionals'))->assertRedirect(route('login'));

    $this->actingAs($agent->refresh())->get(route('professionals'))->assertForbidden();
    $this->actingAs($owner)->get(route('professionals'))->assertOk()->assertSee(__('professionals.title'));
});

test('the menu offers it under Mi negocio', function (): void {
    $this->seed(MenuSeeder::class);

    $item = Menu::query()->where('route_name', 'professionals')->sole();

    expect($item->parent->route_name)->toBe('my-business')
        ->and($item->permission)->toBeNull();
});

test('with nobody loaded it says the agenda works as a single one', function (): void {
    proScreenOwner();

    livewire('professionals.index')
        ->assertSee(__('professionals.list.empty_title'))
        ->assertSee(__('professionals.list.new'));
});

test('with no service that takes a booking it points to Servicios', function (): void {
    $owner = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
    $this->actingAs($owner);

    livewire('professionals.index')->assertSee(__('professionals.needs_services.cta'));
});

test('a client with no business sees the step that unblocks it instead of an error', function (): void {
    $this->actingAs(User::factory()->create(['business_id' => null])->refresh());

    livewire('professionals.index')->assertSee(__('professionals.no_business.title'));
});

test('the owner adds a professional with their own week and what changes for each service', function (): void {
    $owner = proScreenOwner();
    $cut = $owner->business->services()->where('name', 'Corte')->value('id');

    livewire('professionals.index')
        ->call('open')
        ->assertSet('sheetOpen', true)
        ->set('professional.data.name', 'Ana Soto')
        ->set('professional.data.appointment_capacity', '2')
        ->set('professional.data.appointments_per_day', '8')
        ->set('professional.data.uses_business_hours', false)
        ->set('professional.data.week.1.opens_at', '09:00')
        ->set('professional.data.week.1.closes_at', '13:00')
        ->set("professional.data.services.{$cut}.attends", true)
        ->set("professional.data.services.{$cut}.duration_minutes", '20')
        ->set("professional.data.services.{$cut}.price_type", 'from')
        ->set("professional.data.services.{$cut}.price", '15')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('sheetOpen', false)
        ->assertDispatched('notify', type: 'success')
        ->assertSee('Ana Soto');

    $stored = Professional::query()->sole();
    $pivot = $stored->services()->sole()->pivot;

    expect($stored->business_id)->toBe($owner->business_id)
        ->and($stored->appointment_capacity)->toBe(2)
        ->and($stored->hours()->count())->toBe(5)
        ->and($pivot->duration_minutes)->toBe(20)
        ->and($pivot->price_type)->toBe('from');
});

test('a sheet that follows the business hours stores no week', function (): void {
    proScreenOwner();

    livewire('professionals.index')
        ->call('open')
        ->set('professional.data.name', 'Luis')
        ->set('professional.data.uses_business_hours', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Professional::query()->sole()->hours()->count())->toBe(0);
});

test('the sheet says what is wrong instead of saving', function (): void {
    proScreenOwner();

    livewire('professionals.index')
        ->call('open')
        ->set('professional.data.name', '')
        ->set('professional.data.appointment_capacity', '0')
        ->call('save')
        ->assertHasErrors(['name', 'appointment_capacity'])
        ->assertSet('sheetOpen', true);

    expect(Professional::count())->toBe(0);
});

test('two professionals of one business cannot share a name', function (): void {
    $owner = proScreenOwner();
    $owner->business->professionals()->create(['name' => 'Ana']);

    livewire('professionals.index')
        ->call('open')
        ->set('professional.data.name', 'Ana')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('the owner edits a professional and finds their data back', function (): void {
    $owner = proScreenOwner();
    $professional = $owner->business->professionals()->create(['name' => 'Ana', 'appointment_capacity' => 3]);

    livewire('professionals.index')
        ->call('open', $professional->id)
        ->assertSet('professional.data.name', 'Ana')
        ->assertSet('professional.data.appointment_capacity', '3')
        ->set('professional.data.name', 'Ana María')
        ->call('save')
        ->assertHasNoErrors();

    expect($professional->fresh()->name)->toBe('Ana María');
});

test('a professional of another business cannot be opened from here', function (): void {
    proScreenOwner();
    $stranger = Professional::factory()->create();

    livewire('professionals.index')->call('open', $stranger->id)->assertStatus(404);
});

test('removing one with bookings to come is refused, tells why and keeps the sheet open', function (): void {
    $owner = proScreenOwner();
    $professional = $owner->business->professionals()->create(['name' => 'Ana']);
    Appointment::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $professional->id]);

    livewire('professionals.index')
        ->call('open', $professional->id)
        ->call('remove')
        ->assertDispatched('notify', type: 'warning')
        ->assertSet('sheetOpen', true);

    expect(Professional::count())->toBe(1);
});

test('removing one with nothing to come takes it off the list', function (): void {
    $owner = proScreenOwner();
    $professional = $owner->business->professionals()->create(['name' => 'Ana']);

    livewire('professionals.index')
        ->call('open', $professional->id)
        ->call('remove')
        ->assertDispatched('notify', type: 'success')
        ->assertSet('sheetOpen', false)
        ->assertDontSee('Ana');

    expect(Professional::count())->toBe(0);
});

test('the list tells each one hours, services and a switched-off one', function (): void {
    $owner = proScreenOwner();
    $ana = $owner->business->professionals()->create(['name' => 'Ana', 'appointments_per_day' => 8]);
    $owner->business->professionals()->create(['name' => 'Luis', 'is_active' => false]);
    $ana->services()->attach($owner->business->services()->pluck('id'));
    Appointment::factory()->count(3)->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id]);
    Appointment::factory()->cancelled()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id]);
    Appointment::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addMinutes(30)]);

    livewire('professionals.index')
        ->assertSee('Ana')
        ->assertSee('3 turnos por venir')
        ->assertSee('2 servicios')
        ->assertSee(__('professionals.list.business_hours'))
        ->assertSee(__('professionals.list.limits', ['per_day' => 8]))
        ->assertSee(__('professionals.list.inactive'));
});
