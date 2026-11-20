<?php

declare(strict_types=1);

use App\Classes\Main\Releases;
use App\Mail\ReleaseNews;
use App\Models\Business;
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

test('«Novedades» marks what came out since this browser last opened it', function (int $width, bool $dark): void {
    config(['releases' => [
        ['version' => '1.1.0', 'date' => '2026-12-01', 'kind' => 'minor'],
        ['version' => '1.0.0', 'date' => '2026-11-19', 'kind' => 'major'],
    ]]);

    $page = visit('/novedades')->resize($width, 900);
    // A first visit has nothing to compare with: nothing is marked.
    $page->assertMissing('@release-new-1.1.0');

    $page->script("localStorage.setItem('".Releases::SEEN_KEY."', '1.0.0')");

    if ($dark) {
        $page->script("localStorage.setItem('atendia-theme', 'dark')");
    }

    $page->navigate('/novedades')
        ->assertVisible('@release-new-1.1.0')
        ->assertMissing('@release-new-1.0.0')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'releases-since-'.$width.'-'.($dark ? 'dark' : 'light'));

    // The visit itself is now the last one: coming back marks nothing.
    $page->navigate('/novedades')->assertMissing('@release-new-1.1.0');
})->with([[1280, false], [390, true]]);

test('the release mail reads well in a mail client width', function (): void {
    $html = (new ReleaseNews(Business::factory()->create(['name' => 'Peluquería Ana']), [
        'version' => '1.1.0',
        'title' => __('releases.entries')['1.1.0']['title'],
        'items' => __('releases.entries')['1.1.0']['items'],
    ]))->render();

    $page = visit('/novedades')->resize(640, 900);
    $page->script('document.open(); document.write('.json_encode($html).'); document.close();');
    $page->assertSee('Peluquería Ana')->screenshot(fullPage: true, filename: 'releases-mail');
});

test('a version with news lights «Nuevo» and the bell until «Novedades» is opened', function (int $width, bool $dark): void {
    config(['releases' => [
        ['version' => '1.1.0', 'date' => '2026-12-01', 'kind' => 'minor'],
        ['version' => '1.0.0', 'date' => '2026-11-19', 'kind' => 'major'],
    ]]);
    $user = payingUser();
    $user->assignRole('client');
    $this->actingAs($user->refresh());
    $this->artisan('atendia:announce-release');

    $page = visit('/dashboard')->resize($width, 900);

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    $page->click('@bell')
        ->assertSee(__('bell.lines.release_available', ['version' => '1.1.0']))
        ->screenshot(filename: 'releases-bell-'.$width.'-'.($dark ? 'dark' : 'light'));

    $page->navigate('/dashboard');

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    if ($width < 900) {
        $page->click('@sidebar-toggle');
    }

    $page->script("document.querySelector('[data-testid=\"sidebar-version\"]').scrollIntoView({block: 'center'})");
    $page->assertVisible('@sidebar-version-new')
        ->screenshot(filename: 'releases-new-'.$width.'-'.($dark ? 'dark' : 'light'));

    // Opening the page is what turns the mark off.
    $page->navigate('/novedades')->assertSee('1.1.0')->navigate('/dashboard');

    if ($width < 900) {
        $page->click('@sidebar-toggle');
    }

    $page->assertMissing('@sidebar-version-new')->assertNoJavaScriptErrors();
})->with([[1280, false], [390, true]]);
