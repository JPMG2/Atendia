<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Menu;
use App\Models\Service;
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

test('the catalog badges are live per tenant and hide at zero', function (): void {
    $this->seed(MenuSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    Service::factory()->count(3)->create(['business_id' => $user->business_id]);

    $this->actingAs($user);

    Livewire::test('navigation')->assertSeeHtml('menu-badge">3');

    $empty = User::factory()->create();
    $empty->business()->associate(Business::factory()->create())->save();
    $this->actingAs($empty);

    Livewire::test('navigation')->assertDontSeeHtml('menu-badge');
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

test('the shell ships the sidebar collapse toggle and the rail hook', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->actingAs(User::factory()->create()->refresh())
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-testid="sidebar-collapse"', false)
        ->assertSee('atendiaSidebarToggle', false)
        ->assertSee('atendia-sidebar', false)
        ->assertSee(__('menu.sidebar_toggle'));
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

test('the layout repaints the stored theme after every SPA navigation', function (): void {
    // wire:navigate swaps in server HTML that knows no theme: without this
    // listener dark mode "forgets" itself on the first click (owner's catch).
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('livewire:navigated', false);
});

test('the plan upsell talks only to clients, never in the admin panel', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    // The owner has no plan to improve; a client on a trial does.
    $this->actingAs($admin)
        ->get('/admin')
        ->assertDontSee(__('menu.plan_cta'));

    $business = Business::factory()->create();

    $this->actingAs(User::factory()->create(['business_id' => $business->id]))
        ->get(route('dashboard'))
        ->assertSee(__('menu.plan_name', ['plan' => __('plan.names.'.$business->plan()->code)]))
        ->assertSee(__('menu.plan_cta'));
});
