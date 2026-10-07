<?php

declare(strict_types=1);

use App\Models\DemoTag;
use App\Models\SeasonalWindow;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\DemoTagSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $this->seed(DemoTagSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);
});

/*
|--------------------------------------------------------------------------
| Seasons and hero examples, in the catalog hub
|--------------------------------------------------------------------------
| The two masters that make A8 real. The preview earns its own shot: a
| season is approved weeks before it runs, so the only way to know what
| December looks like is to ask for December.
*/

test('the hero examples master lists what the landing is showing', function (): void {
    $page = visit('/admin/catalogs');

    $page->click('Ejemplos del hero')
        ->assertSee('Ferretería El Tornillo')
        ->assertSee('Kiosco El Faro')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-demo-tags');

    // The class is set by hand because inDarkMode() leaves the page in light.
    $page->script('document.documentElement.classList.add("dark")');
    $page->screenshot(filename: 'catalog-demo-tags-dark');
});

test('the preview block fits a phone, whatever the table does', function (): void {
    $page = visit('/admin/catalogs')->resize(390, 844);

    $page->click('Ejemplos del hero')
        ->assertSee('Ferretería El Tornillo')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-demo-tags-mobile');

    // Scoped to what this screen added. The catalog table itself has never
    // stacked on a phone — measured on Redes sociales, which overflows the
    // same way — and that debt belongs to the shared chrome, not here.
    expect((int) $page->script(<<<'JS'
        (() => {
            const card = document.querySelector('.demo-preview');
            return card.scrollWidth - card.clientWidth;
        })()
    JS))->toBe(0);
});

test('the preview answers for a day the season has not reached yet', function (): void {
    $christmas = SeasonalWindow::factory()->create([
        'name' => 'Navidad 2026',
        'starts_at' => SeasonalWindow::today()->addMonths(2),
        'ends_at' => SeasonalWindow::today()->addMonths(2)->addDays(10),
    ]);

    DemoTag::factory()->create([
        'slug' => 'ferreteria',
        'seasonal_window_id' => $christmas->id,
        'label' => 'Ferretería navideña',
        'business_name' => null,
        'noun' => null,
        'chips' => null,
        'pool' => null,
    ]);

    $page = visit('/admin/catalogs');
    $page->click('Ejemplos del hero');

    // Today it is not running, and the preview says so instead of guessing.
    // Read from the pills, not the page: the variant is a row of the table too.
    $page->assertSee('Ese día no hay ninguna temporada');

    expect($page->script('document.querySelector(".demo-preview-pills").innerText'))
        ->not->toContain('Ferretería navideña');

    $page->script('document.querySelector(\'[name="previewDate"]\').value = '
        .json_encode(SeasonalWindow::today()->addMonths(2)->addDays(3)->toDateString())
        .'; document.querySelector(\'[name="previewDate"]\').dispatchEvent(new Event("input", { bubbles: true }))');

    // This sentence lives only in the preview, so waiting on it waits for the
    // roundtrip — the table already says "Navidad 2026" in its own column.
    $page->assertSee('Temporada en curso: Navidad 2026')->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector(".demo-preview-pills").innerText'))
        ->toContain('Ferretería navideña');

    $page->screenshot(filename: 'catalog-demo-tags-preview');
});

test('the seasons master shows whether a season is merely on or actually running', function (): void {
    SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);
    SeasonalWindow::factory()->past()->create(['name' => 'Vacaciones de invierno']);

    $page = visit('/admin/catalogs');

    $page->click('Temporadas')
        ->assertSee('Navidad 2026')
        ->assertSee('En curso')
        ->assertSee('Fuera de fecha')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-seasonal-windows');
});

test('the hero of the landing is drawn from the table, season and all', function (): void {
    $christmas = SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);

    DemoTag::factory()->create([
        'slug' => 'ferreteria',
        'seasonal_window_id' => $christmas->id,
        'label' => 'Ferretería navideña',
        'business_name' => null,
        'noun' => null,
        'chips' => null,
        'pool' => null,
    ]);

    $page = visit('/');

    $page->assertSee('Ferretería navideña')
        ->assertSee('Kiosco')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'landing-hero-seasonal');
});

test('the preview plays the conversation of whichever example is picked', function (): void {
    $page = visit('/admin/catalogs');
    $page->click('Ejemplos del hero');

    // It opens on the first example, which is what a visitor lands on.
    $page->assertSee('¿Tienen cinta de teflón y llave del 14?')->assertNoJavaScriptErrors();

    $page->click('.demo-preview-pill:nth-child(6)');

    $page->assertSee('Mi gato no quiere comer')
        ->assertDontSee('¿Tienen cinta de teflón y llave del 14?')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-demo-tags-phone');
});

test('a season is switched off from its row, and the landing follows', function (): void {
    $christmas = SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);
    DemoTag::factory()->create([
        'slug' => 'ferreteria',
        'seasonal_window_id' => $christmas->id,
        'label' => 'Ferretería navideña',
        'business_name' => null,
        'noun' => null,
        'chips' => null,
        'pool' => null,
    ]);

    $page = visit('/admin/catalogs');
    $page->click('Temporadas')->assertSee('Navidad 2026');

    $page->click('Activa')->assertSee('Inactiva')->assertNoJavaScriptErrors();

    expect($christmas->fresh()->is_active)->toBeFalse();

    visit('/')->assertDontSee('Ferretería navideña')->assertSee('Ferretería');
});

test('a season repeats into next year from its row, off and complete', function (): void {
    $christmas = SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);
    DemoTag::factory()->create([
        'slug' => 'ferreteria',
        'seasonal_window_id' => $christmas->id,
        'label' => 'Ferretería navideña',
        'business_name' => null,
        'noun' => null,
        'chips' => null,
        'pool' => null,
    ]);

    $page = visit('/admin/catalogs');
    $page->click('Temporadas')->assertSee('Navidad 2026');

    $page->click('[aria-label="Repetir el año que viene"]');

    $page->assertSee('Navidad 2027')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-seasonal-repeat');

    $copy = SeasonalWindow::query()->where('name', 'Navidad 2027')->sole();

    expect($copy->is_active)->toBeFalse()
        ->and($copy->demoTags()->sole()->label)->toBe('Ferretería navideña');
});

test('editing a hero example writes it and the landing reads it', function (): void {
    $page = visit('/admin/catalogs');
    $page->click('Ejemplos del hero')->click('Ferretería El Tornillo');

    $page->assertSee('Editando');
    $page->fill('label', 'Ferretería 24h');
    $page->click('Guardar cambios');

    $page->assertSee('Ferretería 24h')->assertNoJavaScriptErrors();

    expect(DemoTag::query()->where('slug', 'ferreteria')->whereNull('seasonal_window_id')->sole()->label)
        ->toBe('Ferretería 24h');
});
