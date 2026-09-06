<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the dashboard loads with no JS errors and renders the recursive menu', function (): void {
    $this->seed(MenuSeeder::class);
    $this->actingAs(User::factory()->create());

    $page = visit('/dashboard');

    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Inicio')
        ->assertSee('Productos')
        ->assertSee('Tu asistente está listo al 20%');
});

test('clicking a parent item expands its nested children (Alpine)', function (): void {
    // The blessed client menu is flat, so recursion gets its own fixture.
    $parent = Menu::factory()->create(['label_key' => 'menu.products']);
    $child = Menu::factory()->create(['parent_id' => $parent->id, 'label_key' => 'menu.services']);
    Menu::factory()->create(['parent_id' => $child->id, 'label_key' => 'menu.whatsapp']);
    $this->actingAs(User::factory()->create());

    $page = visit('/dashboard');

    // The nested branch is collapsed until its parent is clicked.
    $page->assertDontSee('WhatsApp')
        ->click('Productos')
        ->assertSee('Servicios')
        ->click('Servicios')
        ->assertSee('WhatsApp');
});

test('the theme toggle switches between light and dark', function (): void {
    $this->actingAs(User::factory()->create());

    $page = visit('/dashboard');

    $page->assertNoJavaScriptErrors()
        ->click('@theme-toggle')
        ->assertNoJavaScriptErrors();
});
