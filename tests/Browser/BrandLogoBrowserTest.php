<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The brand mark in every header
|--------------------------------------------------------------------------
| One mark, three surfaces: the landing, the client panel and the admin
| panel. The measurement is that the VISIBLE image actually decoded (a path
| that 404s still has an <img>) and that dark ground gets the dark mark.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

/** Whether the visible logo image of a header decoded, in the theme on screen. */
function visibleLogoDecoded(string $selector): string
{
    return "(() => { const shown = [...document.querySelectorAll('{$selector}')].find((i) => i.offsetParent !== null); return !!shown && shown.complete && shown.naturalWidth > 0; })()";
}

test('the landing header carries the mark next to the name, in both themes', function (): void {
    $page = visit('/')->resize(1280, 800);

    $page->assertNoJavaScriptErrors()
        ->assertScript(visibleLogoDecoded('.navbar-logo img'), true)
        ->screenshot(filename: 'brand-logo-landing-light');

    // Set by hand: the landing's toggle sits in a menu that is closed here.
    $page->script('document.documentElement.classList.add("dark")');

    $page->assertScript(visibleLogoDecoded('.navbar-logo img'), true)
        ->assertScript("[...document.querySelectorAll('.navbar-logo img')].find((i) => i.offsetParent !== null).src.includes('logo-mark-dark')", true)
        ->screenshot(filename: 'brand-logo-landing-dark');
});

test('the client sidebar carries the mark next to the name, in both themes', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $this->actingAs($user);

    visit('/dashboard')->resize(1280, 800)
        ->assertNoJavaScriptErrors()
        ->assertScript(visibleLogoDecoded('.sidebar-header img'), true)
        ->screenshot(filename: 'brand-logo-client-light')
        ->click('@theme-toggle')
        ->assertScript(visibleLogoDecoded('.sidebar-header img'), true)
        ->screenshot(filename: 'brand-logo-client-dark');
});

test('the admin sidebar carries the mark next to the name, in both themes', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    visit(route('admin.dashboard'))->resize(1280, 800)
        ->assertNoJavaScriptErrors()
        ->assertScript(visibleLogoDecoded('.sidebar-header img'), true)
        ->screenshot(filename: 'brand-logo-admin-light')
        ->click('@theme-toggle')
        ->assertScript(visibleLogoDecoded('.sidebar-header img'), true)
        ->screenshot(filename: 'brand-logo-admin-dark');
});
