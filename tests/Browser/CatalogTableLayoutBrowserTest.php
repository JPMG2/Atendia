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

    // Asserted on the RULE, not on the exact shade. Two things must hold:
    // the background is the NEUTRAL row token — a semantic colour there fights
    // whatever the row shows (the jade wash went green-on-green with the "Activo"
    // pill) — and the brand lives in the left-edge bar, which is the anchor that
    // makes the jump visible without saturating the row.
    expect($hover[0])->toContain('row-hover')
        ->not->toContain('surface-sunken')
        ->and($hover[1])->toContain('inset')
        ->and($hover[1])->toContain('brand');
});
