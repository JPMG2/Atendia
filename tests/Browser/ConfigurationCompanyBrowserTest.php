<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SocialNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(SocialNetworkSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin);
});

test('the company screen opens on the company data tab with no JS errors', function (): void {
    $page = visit('/admin/company');

    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Razón social')
        ->assertSee('Identificación fiscal')
        // The contact tab exists but its panel stays hidden until it is picked.
        ->assertDontSee('Email de soporte');
});

test('picking the contact tab swaps the panel', function (): void {
    $page = visit('/admin/company');

    $page->click('Contactos y redes')
        ->assertSee('Email de soporte')
        ->assertSee('Redes sociales')
        // The data panel is the one that hides now.
        ->assertDontSee('Razón social');
});

test('a social network row is added from the row itself', function (): void {
    // The list is not a fixed set: adding and removing live on the row, so the
    // whole thing stays on one line and no button floats underneath.
    $page = visit('/admin/company');

    $page->click('Contactos y redes')
        // Only one row exists at first, so the selector is unambiguous.
        ->click('@social-add')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
