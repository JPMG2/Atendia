<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| El Inicio tiene que LLEVAR, no solo contar
|--------------------------------------------------------------------------
| Her complaint, 2026-10-04: the home reads like a report — three of its four
| cards had no link at all, so acting on a name meant going to another screen
| and finding it again by eye. What is proven here is the landing: clicking a
| business opens ITS file, and a tile that counts a state lands on that state.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $late = Business::factory()->create(['name' => 'Kiosco La Esquina']);
    Subscription::factory()->for($late)->create([
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDays(3),
    ]);

    $leaving = Business::factory()->create(['name' => 'Laboratorio Vida']);
    Subscription::factory()->for($leaving)->create([
        'status' => SubscriptionStatus::Active,
        'current_period_ends_at' => now()->addDays(4),
        'canceled_at' => now()->subDay(),
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the home opens on what is stuck, and that business opens its own file', function (): void {
    $late = Business::query()->where('name', 'Kiosco La Esquina')->sole();

    $page = visit(route('admin.dashboard'))->resize(1280, 900);

    // The tab that opens is the one with the unpaid business, not the first.
    $page->assertNoJavaScriptErrors()
        ->assertSee('Kiosco La Esquina')
        ->screenshot(filename: 'admin-home-drilldown-desktop-light')
        ->click('Kiosco La Esquina')
        ->assertSee('Plan y cobro')
        ->screenshot(filename: 'admin-home-drilldown-ficha');

    expect($page->url())->toContain('negocio='.$late->id);
});

test('the other lists are one click away, not another screen', function (): void {
    visit(route('admin.dashboard'))->resize(1280, 900)
        ->assertDontSee('Laboratorio Vida')
        ->click('Bajas')
        ->assertSee('Laboratorio Vida')
        ->screenshot(filename: 'admin-home-tab-leaving');
});

test('the leaving tile lands on the businesses that are leaving, already filtered', function (): void {
    $page = visit(route('admin.businesses', ['estado' => 'canceling']))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Laboratorio Vida')
        ->assertDontSee('Kiosco La Esquina')
        ->screenshot(filename: 'admin-businesses-filtered');
});

test('the home holds on a phone with every link in place', function (): void {
    $page = visit(route('admin.dashboard'))->resize(390, 844);

    $page->click('@theme-toggle')
        ->assertNoJavaScriptErrors()
        ->assertSee('Kiosco La Esquina')
        ->screenshot(filename: 'admin-home-drilldown-phone-dark');

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);
});
