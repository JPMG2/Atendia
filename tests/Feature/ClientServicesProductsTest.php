<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Catalog screens — the shared lock and menu
|--------------------------------------------------------------------------
| Both screens went real: services coverage lives in ClientServicesTest and
| products in ClientProductsTest. What stays here is what they share.
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
