<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Menu;
use App\Models\SuggestedService;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Services and products screens — living mock-ups
|--------------------------------------------------------------------------
| Two states each, zero persistence: the pattern the home blessed. They pin
| the client-panel lock, the two-state switch, the offer-never-require copy
| and the menu children that lead here.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('both screens sit behind the client panel lock', function (): void {
    $this->get(route('my-services'))->assertRedirect(route('login'));
    $this->get(route('my-products'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-services'))->assertSuccessful()->assertSee(__('client.services.title'));
    $this->get(route('my-products'))->assertSuccessful()->assertSee(__('client.products.title'));
});

test('the services screen lists the offer and never demands price or duration', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSee(__('client.services.add'))
        ->assertSee('Corte de caballero')
        // The unpriced and paused rows sit past page one of the grouped list.
        ->call('loadMore')
        // A service without a price wears a soft nudge, not a requirement.
        ->assertSee(__('client.services.no_price'))
        ->assertSee(__('client.services.active'))
        ->assertSee(__('client.services.paused'))
        // Row actions live on the row itself — no modal to open first.
        ->assertSee(__('client.services.edit', ['name' => 'Corte de caballero']))
        ->assertSee(__('client.services.resume', ['name' => 'Peinado para eventos']));
});

test('the completeness meter counts priced rows and nudges without blocking', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSee(__('client.services.meter', ['done' => 19, 'total' => 22]));
});

test('the service sheet slides over the list with price, prep and deposit', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        // The list arrives whole: no sheet is open until a row asks for it.
        ->assertDontSee(__('client.services.sheet_title', ['name' => 'Corte y barba']))
        ->call('edit', 1)
        ->assertSee(__('client.services.sheet_title', ['name' => 'Corte y barba']))
        ->assertSee(__('client.services.field_prep'))
        ->assertSee(__('client.services.deposit_help'))
        ->assertSee(__('client.services.offered'))
        // Cancel wears the universal watch-out colour (owner's call).
        ->assertSeeHtml('btn-danger')
        ->call('closeSheet')
        ->assertDontSee(__('client.services.sheet_title', ['name' => 'Corte y barba']));
});

test('adding a service opens the same sheet blank', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->call('add')
        ->assertSee(__('client.services.sheet_new_title'))
        ->assertSee(__('client.services.field_name'));
});

test('the services list pages with load more instead of rendering everything', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        // Row 9 waits behind the first page of 8; featured repeats add to the
        // loadable total, the way WhatsApp collections repeat a product.
        ->assertSee('Corte de caballero')
        ->assertDontSee('Mechas')
        ->assertSee(__('pagination.showing', ['shown' => 8, 'total' => 25]))
        ->assertSee(__('pagination.load_more'))
        ->call('loadMore')
        ->assertSee('Mechas');
});

test('the search finds rows accent-insensitively and resets the window', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->set('search', 'coloracion')
        ->assertSee('Coloración')
        ->assertDontSee('Corte de caballero')
        // From 3 typed characters the hit glows inside the name (catalog twin).
        ->assertSeeHtml('match-hit')
        ->set('search', 'zzz')
        ->assertSee(__('client.services.no_results'));
});

test('the services group under collapsible category headers with counts', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSeeInOrder(['Cortes', 'Corte de caballero', 'Barba'])
        ->call('toggleGroup', 'Cortes')
        // The header stays; only its rows fold away.
        ->assertSee('Cortes')
        ->assertDontSee('Corte de caballero')
        ->call('toggleGroup', 'Cortes')
        ->assertSee('Corte de caballero');
});

test('searching flattens the shelves: results matter more than the groups', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSeeHtml('toggleGroup')
        ->set('search', 'mechas')
        ->assertSee('Mechas')
        // No group headers while searching: the hits stand alone.
        ->assertDontSeeHtml('toggleGroup');
});

test('the sheet offers the category next to the name', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->call('edit', 0)
        ->assertSee(__('client.services.field_category'))
        ->assertSee(__('client.services.uncategorized'));
});

test('the state filter narrows the list to paused services', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->set('filter', 'paused')
        ->assertSee('Peinado para eventos')
        ->assertDontSee('Corte de caballero');
});

test('the services screen offers the ai hand for the descriptions', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSee(__('client.services.ai_title'))
        ->assertSee(__('client.services.ai_action'));
});

test('the empty services state teaches without inventing suggestions', function (): void {
    // No business yet means no trade: the chips section simply stays out.
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->call('switchTo', 'empty')
        ->assertSee(__('client.services.empty_title'))
        ->assertSee(__('client.services.add'))
        ->assertDontSee(__('client.services.suggestions'))
        // The find-and-operate toolbar belongs to a populated list only.
        ->assertDontSee(__('client.services.search_placeholder'));
});

test('the suggestion chips speak the trade vocabulary straight from the catalog', function (): void {
    $user = User::factory()->create()->refresh();
    $business = Business::factory()->create();
    $user->business()->associate($business)->save();

    $activity = BusinessActivity::factory()->create();
    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Corte de dama']);
    $business->syncActivities($activity->id);

    $this->actingAs($user);

    Livewire::test('goods.index')
        ->call('switchTo', 'empty')
        ->assertSee(__('client.services.suggestions'))
        ->assertSee('Corte de dama');
});

test('the products screen shows the inventory and the import lane side by side', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->assertSee('Alternador Fiat Palio')
        ->assertSee('ALT-021')
        ->assertSee(__('client.products.ai_title'))
        ->assertSee(__('client.products.ai_action'))
        ->assertSee(__('client.products.import_title'))
        ->assertSee(__('client.products.import_last', ['file' => 'repuestos-2026.xlsx', 'rows' => 152]));
});

test('out of stock switches off and shows who waits, it never drops the row', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->assertSee('Correa de distribución')
        ->assertSee(__('client.products.out_note'))
        ->assertSee(trans_choice('client.products.waiting', 3, ['count' => 3]));
});

test('the products search also matches the code and the sheet slides over', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->set('search', 'ngk')
        ->assertSee('Bujía NGK BPR6ES')
        ->assertDontSee('Alternador Fiat Palio')
        ->set('search', '')
        ->call('edit', 0)
        ->assertSee(__('client.products.sheet_title', ['name' => 'Alternador Fiat Palio']))
        ->assertSee(__('client.products.field_code'))
        ->call('closeSheet')
        ->assertDontSee(__('client.products.sheet_title', ['name' => 'Alternador Fiat Palio']));
});

test('the products list pages with load more instead of rendering everything', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->assertDontSee('Kit de distribución completo')
        ->assertSee(__('pagination.showing', ['shown' => 8, 'total' => 24]))
        ->call('loadMore')
        ->call('loadMore')
        ->assertSee('Kit de distribución completo');
});

test('the import review stages rows, flags the broken ones and confirms the rest', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->call('switchTo', 'review')
        ->assertSee(__('client.products.review_summary', ['rows' => 5]))
        ->assertSee(__('client.products.review_bad_price'))
        ->assertSee(__('client.products.review_no_name', ['row' => 18]))
        ->assertSee(__('client.products.review_dupe'))
        // Broken rows stay out; the clean ones never wait for them.
        ->assertSee(__('client.products.review_confirm', ['count' => 3]))
        ->assertSee(__('client.products.review_note'));
});

test('the empty products state leads with the excel import', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->call('switchTo', 'empty')
        ->assertSee(__('client.products.empty_title'))
        ->assertSee(__('client.products.import_cta'))
        ->assertSee(__('client.products.empty_or'));
});

test('the menu groups both screens under the catalog parent, with live badges', function (): void {
    $this->seed(MenuSeeder::class);
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('dashboard'))
        ->assertSee('Catálogo')
        ->assertSeeHtml(route('my-services'))
        ->assertSeeHtml(route('my-products'));

    $catalog = Menu::query()->where('label_key', 'menu.catalog')->firstOrFail();
    $children = Menu::query()->where('parent_id', $catalog->id)->orderBy('sort_order')->get();

    expect($children->pluck('label_key')->all())->toBe(['menu.services', 'menu.products'])
        // The counts mirror the mocks until the real tables land.
        ->and($children->pluck('badge')->all())->toBe(['22', '24']);
});

test('featured services open the list, pinned before the categories', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSeeInOrder([__('client.services.featured_title'), 'Corte y barba', 'Cortes', 'Corte de caballero'])
        ->call('edit', 1)
        ->assertSee(__('client.services.featured'))
        ->assertSee(__('client.services.featured_help'));
});

test('dragging a category reorders the shelves, destacados stays pinned', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->call('reorderCategories', 'Barba', 0)
        ->assertSeeInOrder([__('client.services.featured_title'), 'Barba', 'Cortes'])
        // An unknown key changes nothing: the drop is simply ignored.
        ->call('reorderCategories', 'Nada', 2)
        ->assertSeeInOrder(['Barba', 'Cortes']);
});
