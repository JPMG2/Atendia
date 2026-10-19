<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Exportar in the admin panel — every list screen carries the one menu
|--------------------------------------------------------------------------
| The menu has to be drawn, open, and not push the page sideways. How it
| looks is judged on the shots, which land in tests/Browser/Screenshots.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);
    Business::factory()->create(['name' => 'Laboratorio Vida']);
    Business::factory()->create(['name' => 'Taller Luna']);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());
});

test('the admin screens that list something draw the menu and open it', function (string $route, array $query): void {
    $page = visit(route($route, $query))->resize(1280, 900);

    $page->assertNoJavaScriptErrors();

    expect((int) $page->script('document.querySelectorAll(".export-menu .btn-export").length'))->toBeGreaterThanOrEqual(1);

    $page->click('.export-menu .btn-export')
        ->assertSee(__('reports.menu.formats.pdf.title'))
        ->screenshot(filename: 'admin-export-'.$route);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'businesses' => ['admin.businesses', ['estado' => '']],
    'payments' => ['admin.payments', []],
    'support' => ['admin.support', []],
    'users' => ['admin.users', []],
    'audit' => ['admin.audit', []],
    'alerts' => ['admin.alerts', []],
    'mentions' => ['admin.mentions', []],
    'ai' => ['admin.ai', []],
    'moderation' => ['admin.moderation', []],
    'compliance' => ['admin.compliance', []],
]);

test('on a phone the menu stays inside the page', function (): void {
    $page = visit(route('admin.businesses'))->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->click('.export-menu .btn-export')
        ->assertSee(__('reports.menu.formats.pdf.title'))
        ->screenshot(filename: 'admin-export-phone');

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
});

test('on a phone the catalog menu drops below the title instead of squeezing it', function (): void {
    $page = visit(route('admin.catalogs'))->resize(390, 844)
        ->click('.catalog-item[title="Monedas"]')
        ->wait(2)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-export-catalogs-phone');

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
});

test('a master of the catalog hub carries the menu once it is open', function (): void {
    $page = visit(route('admin.catalogs'))->resize(1280, 900)
        ->click('.catalog-item[title="Monedas"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect((int) $page->script('document.querySelectorAll(".catalog-panel .export-menu").length'))->toBe(1);

    $page->click('.catalog-panel .export-menu .btn-export')
        ->screenshot(filename: 'admin-export-catalogs');
});
