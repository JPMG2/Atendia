<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Products screen — living mock-up (plus the shared lock and menu)
|--------------------------------------------------------------------------
| Products keeps the two-state, zero-persistence mock the home blessed.
| The services screen went real: its coverage lives in ClientServicesTest.
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
