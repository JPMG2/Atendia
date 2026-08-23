<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');
    $this->actingAs($this->admin);
});

test('the rail search filters the masters without a request', function (): void {
    $page = visit('/admin/catalogs');

    $page->assertSee('Monedas')->assertSee('Países');

    $page->fill('catalog-search', 'moned')->assertNoJavaScriptErrors();

    // Asserted on what is actually VISIBLE, not on the page text: the filtered
    // masters stay in the DOM (Alpine only hides them), and the hub's empty
    // state mentions "países" in its own copy. These two retry until Alpine has
    // reacted, so they do not race the keystroke.
    $page->assertVisible('.catalog-item[title="Monedas"]')
        ->assertMissing('.catalog-item[title="Países"]')
        // A group with no match left hides its heading too, instead of leaving
        // "Ubicaciones" standing alone with nothing under it.
        ->assertMissing('.catalog-group:first-child .catalog-group-label');
});

test('a search with no match shows the empty message', function (): void {
    $page = visit('/admin/catalogs');

    $page->fill('catalog-search', 'zzzz')
        ->assertSee(__('catalog.hub.no_matches'))
        ->assertNoJavaScriptErrors();
});

test('the rail fills the screen height and scrolls on its own', function (): void {
    $page = visit('/admin/catalogs');

    // It reaches down like the sidebar does, but never past the viewport: the
    // page does not stretch with the number of masters, the rail scrolls inside.
    $fits = $page->script(
        'const r = document.querySelector(".catalog-rail-slot");'
        .'r.clientHeight > window.innerHeight * 0.7 && r.clientHeight <= window.innerHeight'
    );

    expect($fits)->toBeTrue();
    expect($page->script('getComputedStyle(document.querySelector(".catalog-rail-body")).overflowY'))
        ->toBe('auto');
});

test('opening a master hides the search box while the rail is collapsed', function (): void {
    $page = visit('/admin/catalogs');

    $page->click('Monedas')
        ->assertSee('Divisas ISO 4217 disponibles para precios y facturación.')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector(".catalog-rail-search").clientHeight'))->toBe(0);
});
