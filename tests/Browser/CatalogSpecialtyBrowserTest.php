<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->seed(SpecialtySeeder::class);

    $this->actingAs(User::factory()->create()->syncRoles('admin'));
});

test('the Negocio group opens the specialties master, lists them with their activity and loads the row clicked', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Especialidades')
        ->assertSee('Medicina general')
        ->assertSee('Cocina china')
        ->assertSee('Consultorio médico')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-specialty-list');

    $page->click('Medicina general')
        ->assertSee('Editando')
        ->assertValue('code', 'medicina-general')
        ->assertValue('name', 'Medicina general')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-specialty-form');
});

test('the specialties list does not push the page sideways on a phone', function (): void {
    $page = visit('/admin/catalogs')->resize(390, 844);

    $page->click('Especialidades')->assertSee('Medicina general');

    $overflow = (int) $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect($overflow)->toBeLessThanOrEqual(0);
});
