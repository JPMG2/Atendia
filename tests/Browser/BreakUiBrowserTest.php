<?php

declare(strict_types=1);

use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\PanelNotification;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The worst real data, measured on the real screen
|--------------------------------------------------------------------------
| A long address with no spaces, a figure in bolivars, a name that is a URL:
| each is put where it lands in production and the boxes are compared. A
| screenshot hides a few pixels; a bounding box does not.
*/

const UNBROKEN = 'facturacion.y.cobranzas.de.la.sucursal.principal@consultorio-odontologico-integral.com.ve';

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed([RolesAndPermissionsSeeder::class, MenuSeeder::class]);
});

test('a long address in a catalog row wraps inside the table on a laptop', function (): void {
    $this->seed([CatalogFormSeeder::class, BusinessSectorSeeder::class, BusinessActivitySeeder::class]);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs')->resize(1280, 800);
    $page->click('Actividades')->assertNoJavaScriptErrors();
    // Short columns stay on one line by design; free text lives in the column that takes the rest of the row.
    $page->script('document.querySelector(".catalog-table tbody .catalog-cell-fill").textContent = '.json_encode(UNBROKEN));

    expect((int) $page->script('const w = document.querySelector(".catalog-table-wrap"); w.scrollWidth - w.clientWidth'))->toBeLessThanOrEqual(0);
    $page->screenshot(filename: 'break-catalog-1280');
});

test('a figure in bolivars stays inside its tile on a phone', function (bool $dark): void {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin')->resize(390, 900);

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    $page->script('document.querySelectorAll(".stat-value").forEach(v => v.textContent = "Bs. 12.345.678,90")');

    $escaped = (int) $page->script('Array.from(document.querySelectorAll(".stat-card")).filter(c => { const v = c.querySelector(".stat-value"); return v && v.getBoundingClientRect().right > c.getBoundingClientRect().right - 1; }).length');

    expect($escaped)->toBe(0);
    $page->script('document.querySelector(".stat-card").scrollIntoView({block: "center"})');
    $page->screenshot(filename: 'break-stat-390-'.($dark ? 'dark' : 'light'));
})->with([false, true]);

test('on a phone the panel tells the browser its theme, follows the real screen height and keeps scrolls in their panel', function (): void {
    $user = payingUser();
    $user->assignRole('client');
    $this->actingAs($user);

    $page = visit('/dashboard')->resize(390, 844);
    $page->click('@sidebar-toggle');
    $style = fn (string $selector, string $property): string => (string) $page->script("getComputedStyle(document.querySelector('{$selector}')).{$property}");

    expect($style('html', 'colorScheme'))->toBe('light')
        ->and($style('.sidebar', 'overscrollBehaviorY'))->toBe('contain')
        // The drawer is exactly as tall as the visible screen, toolbar or not.
        ->and((int) $page->script('Math.round(document.querySelector(".sidebar").getBoundingClientRect().height - window.innerHeight)'))->toBe(0);

    $page->script("document.documentElement.classList.add('dark')");
    expect($style('html', 'colorScheme'))->toBe('dark');
    $page->screenshot(filename: 'phone-drawer-390-dark');
});

test('a name with no spaces never runs over the time in the bell', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    $this->actingAs($user);
    PanelNotification::raise($business, PanelNotificationType::CustomerWaiting, 'waiting:1', ['name' => UNBROKEN, 'minutes' => 25], '/conversaciones');

    $page = visit('/dashboard')->resize(390, 900);
    $page->click('@bell')->assertNoJavaScriptErrors();

    // The text's box can sit clear of the time while its letters spill out of it: the spill is what is measured.
    $spill = (int) $page->script('const t = document.querySelector(".bell-text"); t.scrollWidth - t.clientWidth');

    expect($spill)->toBeLessThanOrEqual(0);
    $page->screenshot(filename: 'break-bell-390');
});
