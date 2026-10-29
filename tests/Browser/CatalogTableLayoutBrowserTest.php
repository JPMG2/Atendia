<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\DemoTag;
use App\Models\SeasonalWindow;
use App\Models\User;
use App\Models\WhatsappVariant;
use Database\Seeders\AdoptionNudgeSeeder;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\DemoTagSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\SupportReplySeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');

    foreach ([RolesAndPermissionsSeeder::class, CatalogFormSeeder::class, BusinessSectorSeeder::class,
        BusinessActivitySeeder::class, ServiceModalitySeeder::class, ServiceAttributeSeeder::class,
        ServiceTypeSeeder::class, SupportReplySeeder::class, AdoptionNudgeSeeder::class, SupportTicketKindSeeder::class,
        PlanSeeder::class, DemoTagSeeder::class] as $seeder) {
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
    $page->wait(1)->screenshot(filename: 'catalog-table-1280-'.str($master)->slug());

    $overflow = $page->script(
        'const wrap = document.querySelector(".catalog-table-wrap");'
        .'wrap.scrollWidth - wrap.clientWidth'
    );

    expect($overflow)->toBe(0);
})->with(['Tipos de servicio', 'Modalidades', 'Atributos', 'Países', 'Actividades', 'Planes', 'Feriados', 'Respuestas guardadas', 'Mensajes de adopción', 'Tipos de reporte', 'Ejemplos del hero']);

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
    ['Tipos de servicio', 390], ['Modalidades', 390], ['Atributos', 390], ['Países', 390], ['Actividades', 390], ['Planes', 390], ['Feriados', 390],
    ['Tipos de servicio', 900], ['Atributos', 900], ['Países', 900], ['Planes', 900], ['Feriados', 900],
    ['Respuestas guardadas', 390], ['Mensajes de adopción', 390], ['Tipos de reporte', 390], ['Tipos de reporte', 900],
    ['Ejemplos del hero', 390], ['Ejemplos del hero', 900],
]);

test('Temporadas says what a season changes, amber when nothing, and WhatsApp por temporada greys what it inherits', function (int $width): void {
    $quiet = SeasonalWindow::factory()->create(['name' => 'Pascua', 'notice' => null]);
    $busy = SeasonalWindow::factory()->create(['name' => 'Navidad', 'notice' => 'Atendemos hasta las 14']);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $busy->id, 'phone' => null, 'opening_schedule' => null]);
    $carnival = SeasonalWindow::factory()->create(['name' => 'Carnaval', 'notice' => 'Cerramos el lunes', 'is_active' => false, 'starts_at' => SeasonalWindow::today(), 'ends_at' => SeasonalWindow::today()->addDay()]);
    // Its own number and week: the longest grey pill («Temporada apagada») without adding greyed cells.
    WhatsappVariant::factory()->create(['seasonal_window_id' => $carnival->id, 'phone' => '+58-0414-1112233', 'opening_schedule' => [1 => ['09:00', '13:00']], 'is_active' => true]);
    // The company row is read once per process and the seeders already read it empty. A blank field names what it inherits from it.
    Company::factory()->create(['phone' => '+58-0412-5550199', 'opening_schedule' => [1 => ['09:00', '18:00']]]);
    Once::flush();

    $page = visit('/admin/catalogs')->resize($width, 900);
    $page->click('Temporadas')->assertNoJavaScriptErrors()->assertSee(__('catalog.seasonal_window.content.none'))
        // Switched off inside its dates: the grey pill names the switch, not the dates.
        ->assertSee(__('catalog.seasonal_window.status.off'));
    $page->script('document.querySelector(".catalog-view").scrollIntoView()');
    $page->wait(1)->screenshot(filename: 'catalog-seasons-'.$width);

    // Only the season that changes nothing is amber.
    expect((int) $page->script('Array.from(document.querySelectorAll(".catalog-table td span.block")).filter(s => s.style.color.includes("--warning")).length'))->toBe(1)
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

    $variants = visit('/admin/catalogs')->resize($width, 900);
    $variants->click('WhatsApp por temporada')->assertNoJavaScriptErrors()
        ->assertSee(__('catalog.whatsapp_variant.inherits.phone', ['value' => '+58-0412-5550199']))
        ->assertSee(__('catalog.whatsapp_variant.status.season_off'));
    $variants->script('document.querySelector(".catalog-view").scrollIntoView()');
    $variants->wait(1)->screenshot(filename: 'catalog-variants-'.$width);

    // What a row inherits goes under the season's name: the table never runs past its own box (the page alone would not tell).
    expect((int) $variants->script('document.querySelectorAll(".catalog-table td.text-muted").length'))->toBe(2)
        ->and((int) $variants->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0)
        ->and((int) $variants->script('document.querySelector(".catalog-table").scrollWidth - document.querySelector(".catalog-table-wrap").clientWidth'))->toBeLessThanOrEqual(0)
        // And the door to where it is changed, in a tab of its own.
        ->and($variants->script('document.querySelector(".catalog-table a.row-link[href]").getAttribute("href")'))->toEndWith('/admin/company')
        ->and($variants->script('document.querySelector(".catalog-table a.row-link[href]").getAttribute("target")'))->toBe('_blank');
})->with([1280, 390]);

test('an example that inherits its business says so in grey, and a plan shows its tags in one cell', function (): void {
    $window = SeasonalWindow::factory()->create(['name' => 'Navidad', 'starts_at' => SeasonalWindow::today()->addDays(5), 'ends_at' => SeasonalWindow::today()->addDays(20)]);
    DemoTag::query()->where('slug', 'clinica')->firstOrFail()->replicate()->fill(['seasonal_window_id' => $window->id, 'business_name' => null])->save();

    $page = visit('/admin/catalogs')->resize(390, 900);
    $page->click('Ejemplos del hero')->assertNoJavaScriptErrors()->assertSee(__('catalog.demo_tag.status.inherits'))
        ->assertSee(trans_choice('catalog.demo_tag.status.starts_in', 5, ['name' => 'Navidad', 'count' => 5]));

    // A year-round example that is on carries the hero's door, in a tab of its own; its seasonal copy has no pill to open.
    $doors = $page->script('Array.from(document.querySelectorAll(".catalog-table a.row-link[href]")).map(a => a.getAttribute("href"))');
    expect(array_values(array_filter($doors, fn (string $href): bool => str_ends_with($href, '/?rubro=clinica'))))->toHaveCount(1)
        ->and($page->script('document.querySelector(".catalog-table a.row-link[href]").getAttribute("target")'))->toBe('_blank');
    $page->script('document.querySelector(".catalog-view").scrollIntoView()');
    $page->wait(1)->screenshot(filename: 'catalog-demo-tag-inherits-390');

    // The greyed cell is the one the row says inherits, and only that one.
    expect((int) $page->script('Array.from(document.querySelectorAll(".catalog-table td.text-muted")).filter(td => td.textContent.trim() === '.json_encode(__('catalog.demo_tag.status.inherits')).').length'))->toBe(1)
        ->and((int) $page->script('document.querySelectorAll(".catalog-table td.text-muted").length'))->toBe(1);

    $plans = visit('/admin/catalogs')->resize(1280, 800);
    $plans->click('Planes')->assertNoJavaScriptErrors()->assertSee('US$ 79')->assertSee(__('catalog.plan.tags.featured'))
        ->assertSee(__('catalog.plan.highlights_title'))->assertSee(trans_choice('catalog.plan.highlights.line', 0));
    $plans->wait(1)->screenshot(filename: 'catalog-plan-tags-1280');

    expect((int) $plans->script('Math.max(...Array.from(document.querySelectorAll(".catalog-table tbody tr")).map(tr => tr.querySelectorAll(".status-tag").length))'))->toBe(2);
});

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
