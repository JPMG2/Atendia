<?php

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
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
    $this->seed(MenuSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Inicio')
        ->assertSee('Conversaciones')
        ->assertSee('Crear mi asistente');
});
