<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\CatalogForm;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('the seeders load sectors with their activities', function (): void {
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);

    expect(BusinessSector::count())->toBe(13)
        ->and(BusinessActivity::count())->toBe(74);

    // The examples the master has to cover from day one.
    expect(BusinessActivity::where('code', 'panaderia')->sole()->sector->name)->toBe('Gastronomía')
        ->and(BusinessActivity::where('code', 'farmacia')->sole()->sector->name)->toBe('Salud')
        ->and(BusinessActivity::where('code', 'peluqueria')->sole()->sector->name)->toBe('Belleza y estética')
        ->and(BusinessActivity::where('code', 'kiosco')->sole()->sector->name)->toBe('Comercio')
        // The 2026-09 research: tourism arrived and real estate got promoted
        // out of professional services into a sector of its own.
        ->and(BusinessActivity::where('code', 'hotel')->sole()->sector->name)->toBe('Turismo y hotelería')
        ->and(BusinessActivity::where('code', 'inmobiliaria')->sole()->sector->code)->toBe('inmobiliaria');
});

test('running the seeders twice does not duplicate anything', function (): void {
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);

    expect(BusinessSector::count())->toBe(13)
        ->and(BusinessActivity::count())->toBe(74);
});

test('every seeded activity belongs to a sector and is active', function (): void {
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);

    expect(BusinessActivity::whereNull('business_sector_id')->count())->toBe(0)
        ->and(BusinessActivity::where('is_active', false)->count())->toBe(0)
        ->and(BusinessSector::doesntHave('activities')->count())->toBe(0);
});

test('the hub offers the Negocio group with both masters', function (): void {
    $this->seed(CatalogFormSeeder::class);

    $group = CatalogForm::where('group', 'Negocio')->orderBy('order')->get();

    expect($group->pluck('title')->all())->toBe(['Rubros', 'Actividades'])
        ->and($group->pluck('component')->all())->toBe(['catalog.business-sector', 'catalog.business-activity'])
        ->and($group->every(fn (CatalogForm $form): bool => $form->is_active))->toBeTrue();
});

test('the hub order has no duplicates after inserting the Negocio group', function (): void {
    $this->seed(CatalogFormSeeder::class);

    $orders = CatalogForm::pluck('order');

    expect($orders->duplicates())->toBeEmpty();
});

test('each Negocio master has its permission seeded', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    CatalogForm::where('group', 'Negocio')->get()->each(function (CatalogForm $form): void {
        expect(Permission::where('name', $form->permission_key)->exists())->toBeTrue();
    });
});

test('the hub renders the Negocio group and opens its own editor', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $sectors = CatalogForm::where('component', 'catalog.business-sector')->sole();

    Livewire::actingAs($admin)
        ->test('catalog.manager')
        ->assertSee('Negocio')
        ->assertSee('Rubros')
        ->assertSee('Actividades')
        ->call('select', $sectors->id)
        // No longer falls back to the placeholder: the editor exists.
        ->assertSet('editorComponent', 'catalog.business-sector');
});
