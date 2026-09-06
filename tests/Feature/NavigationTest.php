<?php

declare(strict_types=1);

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
    // The blessed client menu is flat, so recursion gets its own fixture.
    $parent = Menu::factory()->create(['label_key' => 'menu.products']);
    $child = Menu::factory()->create(['parent_id' => $parent->id, 'label_key' => 'menu.services']);
    Menu::factory()->create(['parent_id' => $child->id, 'label_key' => 'menu.whatsapp']);
    Menu::factory()->create(['label_key' => 'menu.settings', 'placement' => 'bottom']);

    Livewire::test('navigation')
        ->assertSee('Productos')     // root with children
        ->assertSee('Servicios')     // depth 2
        ->assertSee('WhatsApp')      // depth 3 — proves recursion
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
        ->assertSee('Tu asistente está listo al 20%');
});

test('the admin panel shows the admin menu, not the client menu', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Usuarios')          // admin menu
        ->assertSee('Configuración')
        ->assertDontSee('Conversaciones'); // client menu
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

test('the plan upsell talks only to clients, never in the admin panel', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    // The owner has no plan to improve; a client on a trial does.
    $this->actingAs($admin)
        ->get('/admin')
        ->assertDontSee(__('menu.plan_name'))
        ->assertDontSee(__('menu.plan_cta'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee(__('menu.plan_name'))
        ->assertSee(__('menu.plan_cta'));
});
