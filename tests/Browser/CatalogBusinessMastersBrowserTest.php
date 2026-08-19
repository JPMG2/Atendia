<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $this->sector = BusinessSector::factory()->create([
        'code' => 'gastronomia',
        'name' => 'Gastronomía',
        'description' => 'Comida y bebida, para el local o para llevar',
        'sort_order' => 1,
    ]);

    $this->activity = BusinessActivity::factory()->for($this->sector, 'sector')->create([
        'code' => 'panaderia',
        'name' => 'Panadería',
        'description' => 'Elaboración y venta de pan',
        'sort_order' => 1,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');
    $this->actingAs($this->admin);
});

test('the Negocio group opens the sector editor and loads the row that was clicked', function (): void {
    $page = visit('/admin/catalogs');

    $page->click('Rubros')->assertSee('Gastronomía');

    $page->click('Gastronomía')
        ->assertSee('Editando')
        ->assertValue('code', 'gastronomia')
        ->assertValue('name', 'Gastronomía')
        ->assertValue('description', 'Comida y bebida, para el local o para llevar')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#if-is_active").checked'))->toBeTrue();

    $page->screenshot();
});

test('saving a sector takes the user back to the list showing the new value', function (): void {
    $page = visit('/admin/catalogs');
    $page->click('Rubros')->click('Gastronomía');

    $page->fill('name', 'Gastronomía y bebidas');
    $page->click('Guardar cambios');

    $page->assertSee('Gastronomía y bebidas')->assertNoJavaScriptErrors();

    expect(visibleCatalogView($page))->toBe('list');
    expect($this->sector->fresh()->name)->toBe('Gastronomía y bebidas');
});

test('the activity editor loads its row with the sector already picked in the combobox', function (): void {
    $page = visit('/admin/catalogs');

    $page->click('Actividades')->assertSee('Panadería');

    $page->click('Panadería')
        ->assertSee('Editando')
        ->assertValue('code', 'panaderia')
        ->assertNoJavaScriptErrors();

    // The combobox shows the LABEL; the id travels in its hidden field.
    expect($page->script('document.querySelector("#if-business_sector_id").value'))->toBe('Gastronomía');

    $page->screenshot();
});

test('a sector is rejected up front when its key is missing, without a round trip', function (): void {
    // The front mirror of the rules has to catch this before the request leaves.
    $page = visit('/admin/catalogs');
    // Por selector y no por texto: "Crear rubro" está dos veces en la pantalla
    // (el botón de la toolbar y el de guardar del pie cuando es un alta).
    $page->click('Rubros')
        ->click('.catalog-toolbar .btn-primary')
        ->fill('name', 'Turismo')
        ->click('.catalog-form-foot .btn-primary')
        ->assertSee('Este campo es obligatorio.')
        ->assertNoJavaScriptErrors();

    expect(visibleCatalogView($page))->toBe('form')
        ->and(BusinessSector::query()->count())->toBe(1);

    $page->screenshot();
});

test('the sector search filters the list client-side', function (): void {
    // El nombre del segundo rubro tiene que ser uno que NO aparezca en la
    // descripción del propio catálogo: ahí ya se leen "Salud" y "Gastronomía".
    BusinessSector::factory()->create(['code' => 'zapateria', 'name' => 'Zapatería', 'sort_order' => 2]);

    $page = visit('/admin/catalogs');
    $page->click('Rubros')->assertSee('Zapatería');

    // Encadenado a propósito: la página es awaitable, así que un fill en su
    // propia sentencia no garantiza haber llegado antes de la assertion.
    $page->fill('q', 'gastro')
        ->assertSee('Gastronomía')
        ->assertDontSee('Zapatería')
        ->assertNoJavaScriptErrors();
});
