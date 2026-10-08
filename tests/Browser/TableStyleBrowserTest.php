<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\SocialNetwork;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The look of a table, measured and shot
|--------------------------------------------------------------------------
| The heading carries a veil of the brand, and the row's key and action are
| the loudest cells of the row. What is checked is the render: a colour that
| is there, a text that can be read on it, and a column that stays inside its
| card. A page that does not overflow says nothing about any of that.
*/

/** Contrast ratio between the heading's text and its ground, read from the page. */
const TABLE_CONTRAST_JS = '(() => {
    const rgb = (c) => { const k = document.createElement("canvas"); k.width = k.height = 1; const x = k.getContext("2d");
        x.fillStyle = "#ff00ff"; x.fillStyle = c; x.fillRect(0, 0, 1, 1); return Array.from(x.getImageData(0, 0, 1, 1).data).slice(0, 3); };
    const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    const th = document.querySelector("%s thead th, %s"); const s = getComputedStyle(th);
    const a = lum(rgb(s.color)), b = lum(rgb(s.backgroundColor));
    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
})()';

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    Business::factory()->count(4)->create();
    SocialNetwork::factory()->count(6)->create();

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the admin table heading has a ground of its own, readable, and the key column leads', function (string $label, int $width, bool $dark): void {
    $page = visit(route('admin.businesses'))->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()->screenshot(filename: 'tables-admin-'.$label.'-'.($dark ? 'dark' : 'light'));

    // The ground is not the card's: with no band the heading is just grey text again.
    $differs = (bool) $page->script(
        '(() => { const th = document.querySelector(".pay-table thead th"); const card = th.closest(".card, [class*=card]");
            return getComputedStyle(th).backgroundColor !== getComputedStyle(card).backgroundColor; })()'
    );

    // Mobile hides the heading and stacks the cells: there is nothing to measure there.
    if ($width >= 992) {
        expect($differs)->toBeTrue();

        $ratio = (float) $page->script(sprintf(TABLE_CONTRAST_JS, '.pay-table', '.pay-table th'));

        expect($ratio)->toBeGreaterThanOrEqual(4.5);
    }

    expect((int) $page->script('document.querySelector(".pay-table td.is-key") ? parseInt(getComputedStyle(document.querySelector(".pay-table td.is-key")).fontWeight) : 0'))->toBeGreaterThanOrEqual(600)
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0)
        ->and((int) $page->script('Array.from(document.querySelectorAll(".pay-table-wrap")).map(w => w.scrollWidth - w.clientWidth).reduce((a, b) => Math.max(a, b), 0)'))->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, false],
    'desktop dark' => ['desktop', 1280, true],
    'tablet light' => ['tablet', 900, false],
    'phone light' => ['phone', 390, false],
    'phone dark' => ['phone', 390, true],
]);

test('the catalog master heading carries the same band and stays opaque while it sticks', function (string $label, bool $dark): void {
    $page = visit(route('admin.catalogs'))->resize(1280, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('Redes sociales')->assertSee('Crear red social');

    $page->screenshot(filename: 'tables-catalog-'.$label);

    $ratio = (float) $page->script(sprintf(TABLE_CONTRAST_JS, '.catalog-table', '.catalog-table th'));
    // Translucent would let the rows scroll visibly under the heading.
    $opaque = (bool) $page->script('(() => { const m = getComputedStyle(document.querySelector(".catalog-table thead th")).backgroundColor.match(/[\d.]+/g); return m.length < 4 || parseFloat(m[3]) === 1; })()');

    expect($ratio)->toBeGreaterThanOrEqual(4.5)
        ->and($opaque)->toBeTrue();
})->with([
    'light' => ['light', false],
    'dark' => ['dark', true],
]);
