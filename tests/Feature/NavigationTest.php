<?php

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

test('the navigation renders the active menu tree to arbitrary depth', function (): void {
    $this->seed(MenuSeeder::class);

    Livewire::test('navigation')
        ->assertSee('Inicio')        // root
        ->assertSee('Productos')     // root with children
        ->assertSee('Catálogo')      // depth 2
        ->assertSee('Categorías')    // depth 2 (has its own child)
        ->assertSee('Activas')       // depth 3 — proves recursion
        ->assertSee('Ajustes');      // bottom group
});

test('the navigation hides inactive menu items', function (): void {
    Menu::factory()->create(['label_key' => 'menu.home']);
    Menu::factory()->inactive()->create(['label_key' => 'menu.help']);

    Livewire::test('navigation')
        ->assertSee('Inicio')
        ->assertDontSee('Ayuda');
});

test('the dashboard renders the sidebar navigation and skeleton for an authenticated user', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Inicio')
        ->assertSee('Conversaciones')
        ->assertSee('Crear mi asistente');
});

test('the admin panel shows the admin menu, not the client menu', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Usuarios')          // menú admin
        ->assertSee('Configuración')
        ->assertDontSee('Conversaciones'); // menú cliente
});

test('the admin dashboard shows the configuration skeleton tiles', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Configuración')
        ->assertSee('Integraciones')
        ->assertSee('Seguridad');
});

test('the client dashboard shows the client menu, not the admin menu', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Conversaciones')
        ->assertDontSee('Usuarios');
});
