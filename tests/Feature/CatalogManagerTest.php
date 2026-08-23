<?php

declare(strict_types=1);

use App\Models\CatalogForm;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');
});

test('the hub lists the seeded masters grouped from catalog_forms', function (): void {
    Livewire::actingAs($this->admin)
        ->test('catalog.manager')
        ->assertSee('Ubicaciones')
        ->assertSee('Facturación')
        ->assertSee('Países')
        ->assertSee('Monedas')
        ->assertSee('Redes sociales');
});

test('the rail has a search box and its own scrollable body', function (): void {
    Livewire::actingAs($this->admin)
        ->test('catalog.manager')
        ->assertSeeHtml('catalogRail(')          // the Alpine rail is mounted
        ->assertSeeHtml('catalog-rail-search')   // fixed head, does not scroll away
        ->assertSeeHtml('catalog-rail-body')     // bounded height + own scroll
        ->assertSeeHtml('groupVisible(')         // a group hides when it has no match left
        ->assertSee(__('catalog.hub.search_placeholder'));
});

test('the hub shows the empty state with no master open by default', function (): void {
    Livewire::actingAs($this->admin)
        ->test('catalog.manager')
        ->assertSet('selectedId', null)
        ->assertSee(__('catalog.hub.empty_title'))
        // El vacío explica QUÉ es un catálogo, no repite la instrucción del
        // encabezado: es lo primero que ve quien entra por primera vez.
        ->assertSee(__('catalog.hub.empty_body'))
        ->assertDontSeeHtml('is-collapsed'); // rail expanded until a master is opened
});

test('selecting a master shows its panel in the second div', function (): void {
    $currency = CatalogForm::where('component', 'catalog.currency')->first();

    Livewire::actingAs($this->admin)
        ->test('catalog.manager')
        ->call('select', $currency->id)
        ->assertSet('selectedId', $currency->id)
        ->assertSee('Divisas ISO 4217 disponibles para precios y facturación.');
});

test('opening a master compacts the rail and closing it expands again', function (): void {
    $currency = CatalogForm::where('component', 'catalog.currency')->first();

    Livewire::actingAs($this->admin)
        ->test('catalog.manager')
        ->call('select', $currency->id)
        ->assertSeeHtml('is-collapsed')          // rail compacts while a master is open
        ->assertSeeHtml('catalog-panel-close')   // the master can be closed
        ->call('close')
        ->assertSet('selectedId', null)
        ->assertDontSeeHtml('is-collapsed');     // rail expands back on close
});

test('every rail item carries its title as a tooltip for the icon-only state', function (): void {
    $currency = CatalogForm::where('component', 'catalog.currency')->first();

    Livewire::actingAs($this->admin)
        ->test('catalog.manager')
        ->call('select', $currency->id)
        ->assertSeeHtml('title="Monedas"')
        ->assertSeeHtml('title="Países"');
});

test('masters are hidden from a user without their permission', function (): void {
    $client = User::factory()->create(); // rol client: sin permisos catalog.*

    Livewire::actingAs($client)
        ->test('catalog.manager')
        ->assertSee(__('catalog.hub.none'))
        ->assertDontSee('Monedas');
});
