<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Load-more is NOT asserted here on purpose: wire:intersect auto-fires while
| the button sits inside the viewport, so on a tall window the list grows on
| its own (that IS the hybrid working). The paging logic lives in the
| feature test; the browser pins search, the sheet, and a clean console.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create());
});

test('the services search narrows live and the sheet slides over and closes', function (): void {
    $page = visit('/servicios');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Corte de caballero')
        ->fill('list_search', 'coloracion')
        // The 300ms debounce plus the round-trip land before asserting.
        ->wait(1)
        ->assertSee('1 servicio')
        ->assertDontSee('Corte de caballero')
        ->fill('list_search', '')
        ->wait(1);

    $page->click('[aria-label="Editar Corte de caballero"]')
        ->assertSee('Ficha de Corte de caballero')
        ->assertSee('Preparación')
        ->screenshot(filename: 'services-sheet-open');

    // Cancel closes without saving; the list is still behind, untouched.
    $page->click('Cancelar')
        ->assertDontSee('Ficha de Corte de caballero')
        ->assertSee('Corte de caballero');
});

test('the products search matches the code in the real browser', function (): void {
    $page = visit('/productos');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Alternador Fiat Palio')
        ->fill('list_search', 'NGK')
        ->wait(1)
        ->assertSee('1 producto')
        ->assertDontSee('Alternador Fiat Palio')
        ->assertSee('Bujía NGK BPR6ES')
        ->screenshot(filename: 'products-search');
});
