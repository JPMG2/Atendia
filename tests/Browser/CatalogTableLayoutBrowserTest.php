<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');

    foreach ([RolesAndPermissionsSeeder::class, CatalogFormSeeder::class, BusinessSectorSeeder::class,
        BusinessActivitySeeder::class, ServiceModalitySeeder::class, ServiceAttributeSeeder::class,
        ServiceTypeSeeder::class] as $seeder) {
        $this->seed($seeder);
    }

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);
});

/**
 * A horizontal scrollbar inside a configuration table is always a design bug, not
 * a feature: the last columns end up hidden and nobody goes looking for them. The
 * fix is fewer, better columns — never a wider table.
 *
 * 1280x800 is a real laptop, and the narrowest place this has to hold: the app
 * sidebar and the catalog rail already eat ~340px before the panel starts.
 */
test('no catalog table overflows its panel on a laptop screen', function (string $master): void {
    $page = visit('/admin/catalogs')->resize(1280, 800);

    $page->click($master)->assertNoJavaScriptErrors();

    $overflow = $page->script(
        'const wrap = document.querySelector(".catalog-table-wrap");'
        .'wrap.scrollWidth - wrap.clientWidth'
    );

    expect($overflow)->toBe(0);
})->with(['Tipos de servicio', 'Modalidades', 'Atributos', 'Países', 'Actividades']);

/**
 * The same rule where it was broken: on a tablet and a phone the table used to push the
 * page 260px wide. Below 992px a row stacks, each cell under its own heading.
 */
test('no catalog table pushes the page sideways on a tablet or a phone', function (string $master, int $width): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    $page->click($master)->assertNoJavaScriptErrors();

    // The panel fades in: shot after it settled and scrolled to the list, not on the way.
    $page->script('document.querySelector(".catalog-view").scrollIntoView()');
    $page->wait(1)->screenshot(filename: 'catalog-table-'.$width.'-'.str($master)->slug());

    // Name the guilty one in the failure: "the page widened" alone sends the search to the whole screen.
    $culprit = (string) $page->script('(() => { const w = window.innerWidth; const bad = Array.from(document.querySelectorAll("body *")).filter(e => e.getBoundingClientRect().right > w + 1).sort((a, b) => b.getBoundingClientRect().right - a.getBoundingClientRect().right).slice(0, 4).map(e => e.tagName + "." + String(e.className).slice(0, 50) + ":" + Math.round(e.getBoundingClientRect().right)); return bad.join(" | "); })()');

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0, "{$master} @ {$width}px: the page widened by {$culprit}")
        ->and((int) $page->script('const w = document.querySelector(".catalog-table-wrap"); w.scrollWidth - w.clientWidth'))->toBeLessThanOrEqual(0, "{$master} @ {$width}px: the table scrolls");

    // Stacked, a cell without its heading is a number with no name.
    $unlabelled = (int) $page->script('Array.from(document.querySelectorAll(".catalog-table tbody tr:not(:last-child) td:not(.catalog-gocell)")).filter(td => !td.dataset.label).length');

    expect($unlabelled)->toBe(0);
})->with([
    ['Tipos de servicio', 390], ['Modalidades', 390], ['Atributos', 390], ['Países', 390], ['Actividades', 390],
    ['Tipos de servicio', 900], ['Atributos', 900], ['Países', 900],
]);

test('the row hover is actually visible, not a 1% tint', function (): void {
    // It used to paint `--surface-sunken`: #F6F8F8 over a #FFFFFF card. With 200
    // rows, not knowing which one you are about to open is the worst bug a table
    // can have. The hover now uses the jade wash plus a bar on the left edge.
    $page = visit('/admin/catalogs')->resize(1280, 800);
    $page->click('Modalidades')->assertSee('Cita / Turno');

    $hover = $page->script(
        'const s = getComputedStyle(document.querySelector(".catalog-table tbody tr"), null);'
        .'const rule = [...document.styleSheets].flatMap(sheet => [...sheet.cssRules])'
        .'  .find(r => r.selectorText === ".catalog-table tbody tr:hover");'
        .'[rule.style.background || rule.style.backgroundColor, rule.style.boxShadow]'
    );

    // Asserted on the RULE, not the exact shade: the background is the NEUTRAL
    // row token, since a semantic colour there fights whatever the row shows,
    // and the brand lives in the left-edge bar.
    expect($hover[0])->toContain('row-hover')
        ->not->toContain('surface-sunken')
        ->and($hover[1])->toContain('inset')
        ->and($hover[1])->toContain('brand');
});
