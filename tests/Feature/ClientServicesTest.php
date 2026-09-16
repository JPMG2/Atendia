<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Service;
use App\Models\ServiceAttribute;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\SuggestedService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Tus servicios" — the real screen
|--------------------------------------------------------------------------
| List, search, shelves and the editing sheet over the tenant's own rows:
| ServiceForm/ServiceCategoryForm validate, the ServiceMenu piece saves
| through the Actions, and nothing here is ever forced on the client.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // Saving the offer publishes knowledge, whose indexing would call the
    // embeddings API.
    Queue::fake();
});

/** A signed-in client whose business the screen reads and writes. */
function servicesScreenUser(): User
{
    $user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();

    test()->actingAs($user);

    return $user;
}

test('without services the screen teaches instead of listing', function (): void {
    servicesScreenUser();

    Livewire::test('goods.index')
        ->assertSee(__('client.services.empty_title'))
        ->assertSee(__('client.services.add'))
        // No trade chosen yet: the chips section simply stays out.
        ->assertDontSee(__('client.services.suggestions'))
        // The find-and-operate toolbar belongs to a populated list only.
        ->assertDontSee(__('client.services.search_placeholder'));
});

test('a suggestion chip opens the sheet with the trade name already typed', function (): void {
    $user = servicesScreenUser();

    $activity = BusinessActivity::factory()->create();
    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Corte de dama']);
    $user->business->syncActivities($activity->id);

    Livewire::test('goods.index')
        ->assertSee(__('client.services.suggestions'))
        ->assertSee('Corte de dama')
        ->call('addFromSuggestion', 'Corte de dama')
        ->assertSet('sheet', 'service')
        ->assertSee(__('client.services.sheet_new_title'));
});

test('the list groups under the client shelves with destacados pinned first', function (): void {
    $user = servicesScreenUser();
    $business = $user->business;

    $cortes = ServiceCategory::factory()->create(['business_id' => $business->id, 'name' => 'Cortes', 'sort_order' => 0]);
    $barba = ServiceCategory::factory()->create(['business_id' => $business->id, 'name' => 'Barba', 'sort_order' => 1]);

    Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte de caballero', 'service_category_id' => $cortes->id]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte y barba', 'service_category_id' => $barba->id, 'is_featured' => true]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Coloración']);

    Livewire::test('goods.index')
        ->assertSeeInOrder([__('client.services.featured_title'), 'Corte y barba', 'Cortes', 'Corte de caballero', 'Barba'])
        // The uncategorized rows close the list under their own bucket.
        ->assertSee(__('client.services.uncategorized'))
        ->assertSee('Coloración')
        ->call('toggleGroup', (string) $cortes->id)
        // The header stays; only its rows fold away.
        ->assertSee('Cortes')
        ->assertDontSee('Corte de caballero');
});

test('the meter counts a price answered by amount or by a free type', function (): void {
    $user = servicesScreenUser();
    $business = $user->business;

    Service::factory()->create(['business_id' => $business->id, 'name' => 'Con precio', 'price' => '12000']);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Consulta gratuita', 'price_type' => 'free']);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Sin resolver']);

    Livewire::test('goods.index')
        ->assertSee(__('client.services.meter', ['done' => 2, 'total' => 3]))
        ->assertSee(__('client.services.no_price'));
});

test('the list pages with load more instead of rendering everything', function (): void {
    $user = servicesScreenUser();

    foreach (range(1, 10) as $i) {
        Service::factory()->create(['business_id' => $user->business->id, 'name' => "Servicio {$i}"]);
    }

    Livewire::test('goods.index')
        ->assertSee('Servicio 1')
        ->assertDontSee('Servicio 9')
        ->assertSee(__('pagination.showing', ['shown' => 8, 'total' => 10]))
        ->call('loadMore')
        ->assertSee('Servicio 9');
});

test('the search finds rows accent-insensitively and flattens the shelves', function (): void {
    $user = servicesScreenUser();
    $business = $user->business;

    $cortes = ServiceCategory::factory()->create(['business_id' => $business->id, 'name' => 'Color']);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Coloración', 'service_category_id' => $cortes->id]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte de caballero']);

    Livewire::test('goods.index')
        ->assertSeeHtml('toggleGroup')
        ->set('search', 'coloracion')
        ->assertSee('Coloración')
        ->assertDontSee('Corte de caballero')
        // From 3 typed characters the hit glows inside the name (catalog twin).
        ->assertSeeHtml('match-hit')
        // No group headers while searching: the hits stand alone.
        ->assertDontSeeHtml('toggleGroup')
        ->set('search', 'zzz')
        ->assertSee(__('client.services.no_results'));
});

test('the state filter narrows the list to paused services', function (): void {
    $user = servicesScreenUser();

    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Peinado para eventos', 'is_active' => false]);
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero']);

    Livewire::test('goods.index')
        ->set('filter', 'paused')
        ->assertSee('Peinado para eventos')
        ->assertDontSee('Corte de caballero');
});

test('creating a service saves the tenant row and adopts the suggested type', function (): void {
    $user = servicesScreenUser();
    $suggestion = SuggestedService::factory()->create(['name' => 'Corte de dama']);

    Livewire::test('goods.index')
        ->call('add')
        ->assertSet('sheet', 'service')
        ->set('form.data.name', 'Corte de dama')
        ->set('form.data.price', '15000')
        ->call('saveService')
        ->assertHasNoErrors()
        ->assertSet('sheet', null);

    $service = $user->business->services()->sole();

    expect($service->name)->toBe('Corte de dama')
        ->and($service->price)->toBe('15000.00')
        ->and($service->service_type_id)->toBe($suggestion->service_type_id);
});

test('editing a service loads the row into the sheet and updates it in place', function (): void {
    $user = servicesScreenUser();
    $service = Service::factory()->create([
        'business_id' => $user->business->id,
        'name' => 'Corte y barba',
        'price' => '16500.00',
    ]);

    Livewire::test('goods.index')
        ->call('edit', $service->id)
        ->assertSee(__('client.services.sheet_title', ['name' => 'Corte y barba']))
        // The decimal cast never leaks its trailing zeros into the input.
        ->assertSet('form.data.price', '16500')
        ->set('form.data.deposit', '5000')
        ->set('form.data.prep_note', 'Llega con el pelo seco.')
        ->call('saveService')
        ->assertHasNoErrors()
        ->assertSet('sheet', null);

    expect($service->refresh()->deposit)->toBe('5000.00')
        ->and($service->prep_note)->toBe('Llega con el pelo seco.');
});

test('the sheet validates without ever demanding price or duration', function (): void {
    servicesScreenUser();

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', '')
        ->call('saveService')
        ->assertHasErrors(['name'])
        ->set('form.data.name', 'Corte de caballero')
        ->set('form.data.price', 'caro')
        ->call('saveService')
        ->assertHasErrors(['price'])
        // A bare name is the floor a service starts from.
        ->set('form.data.price', '')
        ->call('saveService')
        ->assertHasNoErrors();
});

test('a free or to-be-agreed service drops the amount on save', function (): void {
    $user = servicesScreenUser();

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Primera consulta')
        ->set('form.data.price_type', 'free')
        ->set('form.data.price', '9000')
        ->call('saveService')
        ->assertHasNoErrors();

    $service = $user->business->services()->sole();

    expect($service->price)->toBeNull()
        ->and($service->price_type)->toBe('free');
});

test('a duplicated name is rejected inside the business but free across tenants', function (): void {
    $user = servicesScreenUser();
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Coloración']);
    Service::factory()->create(['business_id' => Business::factory()->create()->id, 'name' => 'Mechas']);

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Coloración')
        ->call('saveService')
        ->assertHasErrors(['name'])
        // The neighbour's name is not taken here: tenants never collide.
        ->set('form.data.name', 'Mechas')
        ->call('saveService')
        ->assertHasNoErrors();
});

test('reusing a trashed name restores the old row instead of colliding', function (): void {
    $user = servicesScreenUser();
    $service = Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Coloración']);
    $service->delete();

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Coloración')
        ->call('saveService')
        ->assertHasNoErrors();

    expect($service->refresh()->trashed())->toBeFalse()
        ->and($user->business->services()->count())->toBe(1);
});

test('the row action pauses and resumes without losing the service', function (): void {
    $user = servicesScreenUser();
    $service = Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero']);

    Livewire::test('goods.index')
        ->call('toggle', $service->id)
        ->assertSee(__('client.services.paused'));

    expect($service->refresh()->is_active)->toBeFalse();

    Livewire::test('goods.index')->call('toggle', $service->id);

    expect($service->refresh()->is_active)->toBeTrue();
});

test('a new category lands at the end of the shelves and gets validated', function (): void {
    $user = servicesScreenUser();
    ServiceCategory::factory()->create(['business_id' => $user->business->id, 'name' => 'Cortes', 'sort_order' => 3]);
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero']);

    Livewire::test('goods.index')
        ->call('openCategorySheet')
        ->assertSee(__('client.services.category_sheet_title'))
        ->set('categoryForm.name', 'Cortes')
        ->call('saveCategory')
        ->assertHasErrors(['name'])
        // WhatsApp caps a collection name at 30 characters; so do we.
        ->set('categoryForm.name', str_repeat('a', 31))
        ->call('saveCategory')
        ->assertHasErrors(['name'])
        ->set('categoryForm.name', 'Barba')
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertSet('sheet', null);

    $barba = $user->business->serviceCategories()->where('name', 'Barba')->sole();

    expect($barba->sort_order)->toBe(4);
});

test('dragging a shelf persists the new order', function (): void {
    $user = servicesScreenUser();
    $business = $user->business;

    $cortes = ServiceCategory::factory()->create(['business_id' => $business->id, 'name' => 'Cortes', 'sort_order' => 0]);
    $barba = ServiceCategory::factory()->create(['business_id' => $business->id, 'name' => 'Barba', 'sort_order' => 1]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte de caballero', 'service_category_id' => $cortes->id]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Perfilado de barba', 'service_category_id' => $barba->id]);

    Livewire::test('goods.index')
        ->call('reorderCategories', $barba->id, 0)
        ->assertSeeInOrder(['Barba', 'Cortes']);

    expect($barba->refresh()->sort_order)->toBe(0)
        ->and($cortes->refresh()->sort_order)->toBe(1);
});

test('a shelf from another business can never be assigned', function (): void {
    $user = servicesScreenUser();
    $foreign = ServiceCategory::factory()->create(['business_id' => Business::factory()->create()->id]);

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Corte de caballero')
        ->set('form.data.service_category_id', $foreign->id)
        ->call('saveService')
        ->assertHasErrors(['service_category_id']);
});

test('the deposit speaks neutral spanish and only argentina hears seña', function (): void {
    // "Seña" is Argentina-only vocabulary; the neutral base says "adelanto".
    $user = servicesScreenUser();
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Mechas', 'deposit' => '10000']);

    Livewire::test('goods.index')
        ->assertSee(__('client.services.deposit_short', ['amount' => '10.000']))
        ->assertSee('adelanto');

    app()->setLocale('es_AR');

    expect(__('client.services.field_deposit'))->toBe('Seña')
        ->and(__('client.services.deposit_short', ['amount' => '10.000']))->toBe('seña $ 10.000')
        // The rest of the sheet falls through to the neutral base untouched.
        ->and(__('client.services.field_amount'))->toBe('Monto');
});

test('the sheet only offers a category once one exists', function (): void {
    // A select whose only option is "none" asks a question with no answer.
    $user = servicesScreenUser();

    Livewire::test('goods.index')
        ->call('add')
        ->assertDontSee(__('client.services.field_category'));

    ServiceCategory::factory()->create(['business_id' => $user->business->id, 'name' => 'Cortes']);

    Livewire::test('goods.index')
        ->call('add')
        ->assertSee(__('client.services.field_category'))
        ->assertSee(__('client.services.uncategorized'));
});

test('without shelves the list stays flat and destacados still lead', function (): void {
    // One lonely "Sin categoría" header would be noise; the star promise
    // ("va primero en la lista") holds even without shelves.
    $user = servicesScreenUser();
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero']);
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Peinado de novia', 'is_featured' => true]);

    Livewire::test('goods.index')
        ->assertDontSeeHtml('toggleGroup')
        ->assertDontSee(__('client.services.uncategorized'))
        ->assertSeeInOrder(['Peinado de novia', 'Corte de caballero']);
});

test('a category born from the service sheet comes back already picked', function (): void {
    $user = servicesScreenUser();
    ServiceCategory::factory()->create(['business_id' => $user->business->id, 'name' => 'Cortes']);

    $component = Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Peinado de novia')
        // The detour keeps the typed service safe and waits on the return.
        ->call('openCategoryFromSheet')
        ->assertSet('sheet', 'category')
        ->set('categoryForm.name', 'Peinados')
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertSet('sheet', 'service')
        ->assertSet('form.data.name', 'Peinado de novia');

    $peinados = $user->business->serviceCategories()->where('name', 'Peinados')->sole();

    $component
        ->assertSet('form.data.service_category_id', $peinados->id)
        ->call('saveService')
        ->assertHasNoErrors();

    expect($user->business->services()->sole()->service_category_id)->toBe($peinados->id);
});

test('cancelling the category detour lands back on the service sheet', function (): void {
    $user = servicesScreenUser();
    ServiceCategory::factory()->create(['business_id' => $user->business->id, 'name' => 'Cortes']);

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Peinado de novia')
        ->call('openCategoryFromSheet')
        ->assertSet('sheet', 'category')
        ->call('closeSheet')
        // Never on nothing: the typed service is still waiting.
        ->assertSet('sheet', 'service')
        ->assertSet('form.data.name', 'Peinado de novia')
        ->assertSet('form.data.service_category_id', null);
});

test('picking a type grows the sheet with its curated fields and stores the values', function (): void {
    $user = servicesScreenUser();
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero', 'service_type_id' => null]);

    $type = ServiceType::factory()->create(['name' => 'Mesa', 'is_active' => true]);
    $seats = ServiceAttribute::factory()->create(['name' => 'Personas', 'data_type' => 'number', 'is_active' => true]);
    $fasting = ServiceAttribute::factory()->create(['name' => 'Requiere ayuno', 'data_type' => 'boolean', 'is_active' => true]);

    // The pivot overrides win over the attribute's own words (Drupal pattern).
    $type->serviceAttributes()->attach($seats->id, ['is_required' => true, 'sort_order' => 0, 'label_override' => 'Comensales']);
    $type->serviceAttributes()->attach($fasting->id, ['is_required' => false, 'sort_order' => 1]);

    Livewire::test('goods.index')
        ->call('add')
        ->assertSee(__('client.services.field_type'))
        ->set('form.data.name', 'Mesa para dos')
        ->set('form.data.service_type_id', $type->id)
        ->assertSee('Comensales')
        ->assertSee('Requiere ayuno')
        // The required curated field holds the save until it is answered.
        ->call('saveService')
        ->assertHasErrors(['attribute_values.'.$seats->id])
        ->set('form.data.attribute_values.'.$seats->id, '4')
        ->call('saveService')
        ->assertHasNoErrors();

    $service = $user->business->services()->where('name', 'Mesa para dos')->sole();

    // Values key by attribute id, never by code: renames cannot orphan them.
    expect($service->service_type_id)->toBe($type->id)
        ->and($service->attribute_values)->toBe([(string) $seats->id => '4']);
});

test('without catalog types the sheet simply keeps its universal core', function (): void {
    $user = servicesScreenUser();
    Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero', 'service_type_id' => null]);

    Livewire::test('goods.index')
        ->call('add')
        ->assertDontSee(__('client.services.field_type'));
});

test('closing the sheet never saves', function (): void {
    $user = servicesScreenUser();
    $service = Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Corte de caballero']);

    Livewire::test('goods.index')
        ->call('edit', $service->id)
        ->set('form.data.name', 'Otro nombre')
        ->call('closeSheet')
        ->assertSet('sheet', null);

    expect($service->refresh()->name)->toBe('Corte de caballero');
});
