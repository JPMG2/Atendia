<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);
    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);
});

test('the currency search filters the table client-side without hitting the server', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');

    $page->click('Monedas')
        ->assertSee('Peso Argentino')
        ->assertSee('Dólar Estadounidense')
        ->assertSee('Euro');

    // Typing narrows the rows purely in Alpine — no wire request, no page change.
    $page->fill('q', 'peso')
        ->assertSee('Peso Argentino')
        ->assertDontSee('Dólar Estadounidense')
        ->assertDontSee('Euro')
        ->assertNoJavaScriptErrors();
});
