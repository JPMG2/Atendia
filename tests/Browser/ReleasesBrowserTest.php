<?php

declare(strict_types=1);

use App\Classes\Main\Releases;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The version the customers see, and «Novedades»
|--------------------------------------------------------------------------
| The landing footer and both panels print it, and it opens what each
| version brought. How it reads is judged on the shots.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlanSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);
});

test('the landing footer shows the version and opens «Novedades»', function (int $width, bool $dark): void {
    $version = (new Releases)->current;
    $page = visit('/')->resize($width, 900);

    if ($dark) {
        // The landing's toggle has no test id; the stored choice holds across both pages.
        $page->script("localStorage.setItem('atendia-theme', 'dark')");
        $page->navigate('/');
    }

    $page->script("document.querySelector('[data-testid=\"footer-version\"]').scrollIntoView({block: 'center'})");
    $page->assertSee('v'.$version)
        ->assertAttribute('[data-testid="footer-version"]', 'href', route('releases'))
        ->screenshot(filename: 'releases-footer-'.$width.'-'.($dark ? 'dark' : 'light'));

    // On a phone the sales chat bubble floats over the footer corner: the page is opened by its address.
    $page->navigate('/novedades')
        ->assertSee(__('releases.version', ['version' => $version]))
        ->assertSee(__('releases.current'))
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'releases-page-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[1280, true], [390, false]]);

test('both panels show the version at the foot of the menu', function (string $panel, int $width, bool $dark): void {
    $version = (new Releases)->current;
    $user = $panel === 'client' ? payingUser() : User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($panel === 'client' ? 'client' : 'admin');
    $this->actingAs($user->refresh());

    $page = visit($panel === 'client' ? '/dashboard' : '/admin')->resize($width, 900);

    if ($width < 900) {
        $page->click('@sidebar-toggle');
    }

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    $page->script("document.querySelector('[data-testid=\"sidebar-version\"]').scrollIntoView({block: 'center'})");
    $page->assertSee('v'.$version)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'releases-sidebar-'.$panel.'-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([
    'client wide light' => ['client', 1280, false],
    'admin wide dark' => ['admin', 1280, true],
    'client phone dark' => ['client', 390, true],
]);
