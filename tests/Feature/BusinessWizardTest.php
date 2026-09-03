<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Province;
use App\Models\SuggestedService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Client onboarding wizard
|--------------------------------------------------------------------------
| The identity and connection steps write real data through BusinessForm;
| the parent still mirrors state by events for the tabs, the checklist and
| the phone preview. These tests pin the lock on the route, the shell, the
| event protocol and the catalog wiring.
*/

function actingAsClient(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $client = User::factory()->create()->refresh();
    $client->assignRole('client');
    test()->actingAs($client);

    return $client;
}

// The wizard writes real data, so the client-panel lock is back on the route.
test('a guest is sent to login instead of the wizard', function (): void {
    $this->get('/alta')->assertRedirect(route('login'));
});

test('registering lands straight in the wizard', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->post('/register', [
        'name' => 'Ana Prueba',
        'email' => 'ana@prueba.com',
        'password' => 'Password!123',
        'password_confirmation' => 'Password!123',
    ])->assertRedirect(route('onboarding', absolute: false));
});

test('the wizard shows every mounted step', function (): void {
    actingAsClient();

    $this->get('/alta')
        ->assertOk()
        ->assertSee(__('wizard.steps.2.heading'))
        ->assertSee(__('wizard.steps.5.heading'))
        ->assertSee('<title>Alta de cliente</title>', false);
});

// Its own layout must not cost system chrome: same shared component as the shell.
test('the wizard keeps the theme toggle from the main layout', function (): void {
    actingAsClient();

    $this->get('/alta')
        ->assertOk()
        ->assertSee('data-testid="theme-toggle"', false);
});

// The account is Breeze's register, so it has no panel here: the wizard
// always opens on step two with "your account" already ticked.
test('the wizard always opens on step two with the account ticked', function (): void {
    Livewire::test('business.wizard')
        ->assertSet('step', 2)
        ->assertSet('done', [1]);
});

test('the tabs cannot navigate back into the account step', function (): void {
    Livewire::test('business.wizard')
        ->call('goToStep', 1)
        ->assertSet('step', 2);
});

test('a completed step marks itself done and moves to the next one', function (): void {
    Livewire::test('business.wizard')
        ->dispatch('wizard:step-completed', step: 2)
        ->assertSet('step', 3)
        ->assertSet('done', [1, 2]);
});

test('a skipped step moves on without marking itself done', function (): void {
    Livewire::test('business.wizard')
        ->set('step', 4)
        ->dispatch('wizard:step-completed', step: 4, skipped: true)
        ->assertSet('step', 5)
        ->assertSet('done', [1]);
});

test('the business name travels live into the preview', function (): void {
    Livewire::test('business.wizard')
        ->dispatch('wizard:name-updated', name: 'Clínica Vida')
        ->assertSet('businessName', 'Clínica Vida')
        ->assertDispatched('preview-updated');
});

test('adding a service marks step three done on its own', function (): void {
    Livewire::test('business.wizard')
        ->dispatch('wizard:services-updated', services: ['Ecodoppler'])
        ->assertSet('services', ['Ecodoppler'])
        ->assertSet('done', [1, 3]);
});

test('finishing step five connected shows the connected closing text', function (): void {
    Livewire::test('business.wizard')
        ->set('step', 5)
        ->dispatch('wizard:step-completed', step: 5, connected: true)
        ->assertSet('step', 6)
        ->assertSet('connected', true)
        ->assertSee(__('wizard.done.text_connected'));
});

test('skipping the connection shows the pending closing text', function (): void {
    Livewire::test('business.wizard')
        ->set('step', 5)
        ->dispatch('wizard:step-completed', step: 5, skipped: true)
        ->assertSet('step', 6)
        ->assertSee(__('wizard.done.text_pending'));
});

// The chips come from the CATALOG, demand-ordered: no hardcoded sector list.
test('the business step offers the active catalog sectors in demand order', function (): void {
    BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud', 'sort_order' => 2]);
    BusinessSector::factory()->create(['code' => 'comercio', 'name' => 'Comercio', 'sort_order' => 1]);
    BusinessSector::factory()->create(['code' => 'dormido', 'name' => 'Rubro dormido', 'is_active' => false]);

    Livewire::test('business.step-business')
        ->assertSeeInOrder(['Comercio', 'Salud'])
        ->assertDontSee('Rubro dormido');
});

test('the business step reports the name live and the chosen sector', function (): void {
    Livewire::test('business.step-business')
        ->set('form.data.name', 'Clínica Vida')
        ->assertDispatched('wizard:name-updated', name: 'Clínica Vida')
        ->call('choose', 'salud')
        ->assertDispatched('wizard:sector-chosen', sector: 'salud');
});

// Picking a sector opens the second question with ITS trades only; picking
// a sector with a single trade answers itself.
test('choosing a sector offers its trades, and a lone trade picks itself', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);
    BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería', 'business_sector_id' => $sector->id]);
    BusinessActivity::factory()->create(['code' => 'heladeria', 'name' => 'Heladería', 'business_sector_id' => $sector->id]);
    BusinessActivity::factory()->create(['code' => 'ajeno', 'name' => 'Oficio ajeno']);

    $lone = BusinessSector::factory()->create(['code' => 'otro', 'name' => 'Otro']);
    BusinessActivity::factory()->create(['code' => 'otra-actividad', 'business_sector_id' => $lone->id]);

    Livewire::test('business.step-business')
        ->call('choose', 'gastronomia')
        ->assertSee('Panadería')
        ->assertSee('Heladería')
        ->assertDontSee('Oficio ajeno')
        ->assertSet('form.data.activity', null)
        ->call('choose', 'otro')
        ->assertSet('form.data.activity', 'otra-actividad')
        ->assertDispatched('wizard:activity-chosen', activity: 'otra-actividad');
});

test('the chosen activity narrows the suggestions to its own trade', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia']);
    $bakery = BusinessActivity::factory()->create(['code' => 'panaderia', 'business_sector_id' => $sector->id]);
    $iceCream = BusinessActivity::factory()->create(['code' => 'heladeria', 'business_sector_id' => $sector->id]);

    SuggestedService::factory()->create(['business_activity_id' => $bakery->id, 'name' => 'Pan del día']);
    SuggestedService::factory()->create(['business_activity_id' => $iceCream->id, 'name' => 'Cuarto kilo de helado']);

    Livewire::test('business.step-services', ['sector' => 'gastronomia', 'activity' => 'heladeria'])
        ->assertSee('Cuarto kilo de helado')
        ->assertDontSee('Pan del día');
});

// The wiring proof: Continuar runs the form, the tenant lands in the table
// and only then does the wizard move on.
test('finishing the business step creates the tenant and advances', function (): void {
    $client = actingAsClient();

    $sector = BusinessSector::factory()->create(['code' => 'salud']);
    $activity = BusinessActivity::factory()->create(['code' => 'consultorio-medico', 'business_sector_id' => $sector->id]);
    $province = Province::factory()->create();

    Livewire::test('business.step-business')
        ->set('form.data.name', 'Clínica Vida')
        ->set('form.data.country_id', $province->country_id)
        ->set('form.data.province_id', $province->id)
        ->call('choose', $sector->code)
        ->call('chooseActivity', $activity->code)
        ->call('finish')
        ->assertDispatched('wizard:step-completed', step: 2);

    expect(Business::sole()->name)->toBe('Clínica Vida')
        ->and(Business::sole()->primaryActivity()->code)->toBe('consultorio-medico')
        ->and($client->refresh()->business_id)->toBe(Business::sole()->id);
});

test('a business step without sector shows the error and stays put', function (): void {
    actingAsClient();

    $province = Province::factory()->create();

    Livewire::test('business.step-business')
        ->set('form.data.name', 'Clínica Vida')
        ->set('form.data.country_id', $province->country_id)
        ->set('form.data.province_id', $province->id)
        ->call('finish')
        ->assertHasErrors('sector')
        ->assertNotDispatched('wizard:step-completed');

    expect(Business::count())->toBe(0);
});

test('the services step adds without duplicates and removes by index', function (): void {
    Livewire::test('business.step-services', ['sector' => 'salud'])
        ->set('draft', 'Ecodoppler')
        ->call('add')
        ->assertSet('services', ['Ecodoppler'])
        ->call('add', 'Ecodoppler')
        ->assertSet('services', ['Ecodoppler'])
        ->call('add', 'Control')
        ->call('remove', 0)
        ->assertSet('services', ['Control'])
        ->assertDispatched('wizard:services-updated');
});

// Sector → its activities → their CONCRETE suggested services (GBP-style):
// the chips speak the client's language, never the catalog's abstractions.
test('the services step suggests the sector\'s concrete services', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'salud']);
    $activity = BusinessActivity::factory()->create(['business_sector_id' => $sector->id]);

    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Ecodoppler doppler']);
    SuggestedService::factory()->create(['name' => 'Servicio de otro rubro']);

    Livewire::test('business.step-services', ['sector' => 'salud'])
        ->assertSee('Ecodoppler doppler')
        ->assertDontSee('Servicio de otro rubro');
});

test('a sector with nothing to suggest hides the suggestion block', function (): void {
    BusinessSector::factory()->create(['code' => 'automotor']);

    Livewire::test('business.step-services', ['sector' => 'automotor'])
        ->assertDontSee(__('wizard.services.suggest'));
});

test('the products step simulates the import and reports it', function (): void {
    Livewire::test('business.step-products')
        ->call('simulateImport')
        ->assertSet('imported', true)
        ->assertDispatched('wizard:products-imported')
        ->assertSee(__('wizard.products.import_ok'));
});

test('the connection step saves numbers and email before reporting the connection', function (): void {
    $client = actingAsClient();
    $business = Business::factory()->create();
    $client->business()->associate($business)->save();

    Livewire::test('business.step-whatsapp')
        ->assertSee(__('wizard.fields.whatsapp_number'))
        ->assertSee(__('wizard.fields.business_email'))
        ->set('form.data.whatsapp_number', '+54 9 341 512 4408')
        ->set('form.data.email', 'hola@laesquina.com')
        ->call('finish')
        ->assertDispatched('wizard:step-completed', step: 5, skipped: false, connected: true);

    expect($business->refresh()->whatsapp_number)->toBe('+54 9 341 512 4408')
        ->and($business->email)->toBe('hola@laesquina.com');
});

test('skipping the connection saves nothing and still moves on', function (): void {
    actingAsClient();

    Livewire::test('business.step-whatsapp')
        ->set('form.data.whatsapp_number', '+54 9 341 512 4408')
        ->call('finish', true)
        ->assertDispatched('wizard:step-completed', step: 5, skipped: true, connected: false);

    expect(Business::count())->toBe(0);
});
