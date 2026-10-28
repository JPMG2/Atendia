<?php

declare(strict_types=1);

use App\Dto\CatalogColumnDto;
use App\Models\Business;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\CountryHolidaySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Holidays master, in a real browser
|--------------------------------------------------------------------------
| The list she reads to see what each country has, and the form that asks
| only for the date of the shape she picked — the one-year holiday last.
*/

beforeEach(function (): void {
    app()->setLocale('es');

    foreach ([RolesAndPermissionsSeeder::class, CatalogFormSeeder::class, CurrencySeeder::class,
        CountrySeeder::class, CountryHolidaySeeder::class] as $seeder) {
        $this->seed($seeder);
    }

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);
});

test('the list says each day in words, with its shape', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Feriados')->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('Día de la Independencia')
        ->assertSee('9 de julio')
        ->assertSee('2 días antes de Pascua')
        ->assertSee('Desde Pascua')
        ->screenshot(filename: 'catalog-holidays-list');
});

test('the list opens with the next holiday of each country and filters by country with one tap, at every width', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('Feriados')->wait(1)->assertNoJavaScriptErrors();

    $count = fn (): string => (string) $page->script('document.querySelector(".catalog-count b").textContent.trim()');
    $tap = fn (string $chip) => $page->script("[...document.querySelectorAll('.catalog-filter button')].find((b) => b.textContent.trim() === ".json_encode($chip).').click()');
    $shown = fn (): array => (array) $page->script('[...new Set([...document.querySelectorAll(".catalog-table tbody tr")].filter((r) => r.offsetParent !== null).map((r) => r.children[0].textContent.trim()))]');

    // One line per country, from the model: the same lines the phone reads.
    $page->assertSee('Próximo feriado de cada país')->assertPresent('@holiday-upcoming');
    expect($page->script('document.querySelectorAll("[data-testid=holiday-upcoming] li").length'))->toBe(2)
        ->and($count())->toBe('24');

    $tap('Venezuela');
    $page->wait(0.4);
    expect($count())->toBe('12')
        ->and($shown())->toBe(['Venezuela'])
        ->and($page->script("document.querySelector('.catalog-filter button[aria-pressed=\"true\"]').textContent.trim()"))->toBe('Venezuela');
    $page->screenshot(filename: 'catalog-holidays-chips-'.$label);

    // The search works inside the chip, and "Todos" gives the whole list back.
    $page->type('q', 'navidad')->wait(0.4);
    expect($count())->toBe('1');
    $page->type('q', '');
    $tap('Todos');
    $page->wait(0.4);
    expect($count())->toBe('24')
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop-light' => ['desktop-light', 1280, false],
    'phone-dark' => ['phone-dark', 390, true],
]);

test('a row saved outside the chosen chip gives "Todos" back, and so does a chip whose country is gone', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);
    $page->click('Feriados')->wait(1);

    $tap = fn (string $chip) => $page->script("[...document.querySelectorAll('.catalog-filter button')].find((b) => b.textContent.trim() === ".json_encode($chip).').click()');
    $pressed = fn (): string => (string) $page->script("document.querySelector('.catalog-filter button[aria-pressed=\"true\"]').textContent.trim()");
    // What the server sends after a save: every row, as `catalog-rows-refreshed` hands them over.
    $refresh = fn (string $js) => $page->script('(() => { const m = document.querySelector(".catalog-master"); const rows = Alpine.$data(m).items.slice(); '.$js.'; m.dispatchEvent(new CustomEvent("catalog-rows-refreshed", { detail: { rows } })); })()');

    $tap('Venezuela');
    $page->wait(0.3);

    // Editing a Venezuelan holiday keeps the chip: the saved row is still inside it.
    $refresh('rows[rows.length - 1] = { ...rows[rows.length - 1], name: "Navidad editada" }');
    $page->wait(0.3);
    expect($pressed())->toBe('Venezuela');

    // A new Argentinian holiday saved while "Venezuela" is on: the list opens up so the saved row shows.
    $refresh('rows.push({ id: 9999, country: "Argentina", name: "Feriado nuevo", kind: "fixed", when: "1 de marzo", active: true })');
    $page->wait(0.3);
    expect($pressed())->toBe('Todos')
        ->and($page->script('document.querySelector(".catalog-count b").textContent.trim()'))->toBe('25');

    // A chip whose country no longer has a row reads as "Todos", never an empty list with nothing pressed.
    $tap('Venezuela');
    $refresh('rows.splice(0, rows.length, ...rows.filter((r) => r.country !== "Venezuela"))');
    $page->wait(0.3);
    expect($page->script('document.querySelector(".catalog-count b").textContent.trim()'))->toBe('13');
});

test('the chips are in the same order in the browser and on the server, the one the phone gets', function (): void {
    $names = ['Zuliandia', 'Ñandú', 'Nicaragua', 'Guinea Ecuatorial', 'Guinea', 'Ázkaria', 'Øresund', 'Œland', 'Oeste'];
    $page = visit('/admin/catalogs')->resize(1280, 900);

    // The web sorts with the browser's Spanish collator; the API with PHP's. One list, one order.
    $browser = (array) $page->script('const c = new Intl.Collator("es"); '.json_encode($names).'.sort((a, b) => c.compare(a, b))');

    expect($browser)->toBe(CatalogColumnDto::spanishOrder($names))
        ->and(array_slice($browser, 0, 3))->toBe(['Ázkaria', 'Guinea', 'Guinea Ecuatorial'])
        ->and(array_search('Ñandú', $browser, true))->toBeGreaterThan(array_search('Nicaragua', $browser, true));
});

test('Países says how many provinces and holidays each country has, so the one still missing them shows', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Países')->wait(1)->assertNoJavaScriptErrors()->assertSee('Provincias');

    $venezuela = (array) $page->script('[...document.querySelectorAll(".catalog-table tbody tr")].map((r) => [...r.children].map((c) => c.textContent.trim())).find((cells) => cells.includes("Venezuela"))');
    $page->screenshot(filename: 'catalog-countries-counts');

    // Holidays seeded for Venezuela only through CountryHolidaySeeder: 12 of them, and no provinces in this base.
    expect($venezuela)->toContain('12')
        ->and((int) $page->script('const w = document.querySelector(".catalog-table-wrap"); w.scrollWidth - w.clientWidth'))->toBeLessThanOrEqual(0);
});

test('the year view shows the twelve months and the days in words, at every width', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('Feriados')->wait(0.8)->click('@holiday-year')->wait(1);

    $page->assertSee('Feriados de '.now()->year)
        ->assertSee('Puente posible')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-holidays-year-'.$label);

    // A panel trapped inside the master's card (an ancestor that makes itself the fixed box) reads as clipped.
    $trap = $page->script('(() => { for (let el = document.querySelector(".slide-over-backdrop").parentElement; el; el = el.parentElement) { const s = getComputedStyle(el); if (s.transform !== "none" || s.filter !== "none" || s.contain !== "none" || s.willChange !== "auto" || s.perspective !== "none" || s.backdropFilter !== "none") { return el.className + " | " + s.transform + " | " + s.contain + " | " + s.willChange; } } return ""; })()');
    expect($trap)->toBe('');

    expect((int) $page->script('document.querySelectorAll(".ycal-month").length'))->toBe(12)
        ->and((int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length'))->toBeGreaterThan(5)
        // The panel scrolls down, never sideways; the page behind does not move either.
        ->and((int) $page->script('document.querySelector(".slide-over-body").scrollWidth - document.querySelector(".slide-over-body").clientWidth'))->toBe(0)
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop' => ['desktop', 1280, false],
    'dark' => ['dark', 1280, true],
    'phone' => ['phone', 390, false],
]);

test('a weekday asks before it becomes a one-year holiday, and cancelling writes nothing', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Feriados')->wait(0.8)->click('@holiday-year')->wait(1);

    // Recurring holidays are edited from the list: only an empty weekday or a one-year day is a button.
    expect((int) $page->script('document.querySelectorAll(".ycal-day.is-holiday:not(.is-action)").length'))->toBeGreaterThan(5)
        ->and((int) $page->script('document.querySelectorAll("button.ycal-day.is-holiday").length'))->toBe(0);

    $before = (int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length');

    $page->script('document.querySelector("button.ycal-day.is-action:not(.is-holiday)").click()');
    $page->wait(0.6)->assertSee('como feriado puente')->screenshot(filename: 'catalog-holidays-bridge-confirm');

    // Escape is "no": nothing was created.
    $page->script('document.dispatchEvent(new KeyboardEvent("keydown", {key: "Escape", bubbles: true}))');
    $page->wait(0.5);
    expect((int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length'))->toBe($before);

    $page->script('document.querySelector("button.ycal-day.is-action:not(.is-holiday)").click()');
    $page->wait(0.6)->click('Marcar el día')->wait(1.2);

    expect((int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length'))->toBe($before + 1)
        ->and((int) $page->script('document.querySelectorAll("button.ycal-day.is-holiday").length'))->toBe(1);

    $page->assertNoJavaScriptErrors()->screenshot(filename: 'catalog-holidays-bridge-marked');
});

test('dragging over the days marks a long bridge at once and then offers the mail to the businesses', function (): void {
    foreach (['ARG', 'VEN'] as $code) {
        $business = Business::factory()->create(['country_id' => Country::query()->where('code', $code)->value('id'), 'is_demo' => false]);
        User::factory()->create(['business_id' => $business->id, 'email' => strtolower($code).'@kiosco.test']);
    }

    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Feriados')->wait(0.8)->click('@holiday-year')->wait(1);

    $before = (int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length');

    // Mouse down on the first free weekday and move to the sixth: a week and a weekend in between.
    $page->script(<<<'JS'
        (() => {
            const days = [...document.querySelectorAll('button.ycal-day[data-free]')];
            const at = (cell) => { const box = cell.getBoundingClientRect(); return { clientX: box.x + box.width / 2, clientY: box.y + box.height / 2 }; };

            days[0].dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, button: 0, ...at(days[0]) }));
            days[5].dispatchEvent(new PointerEvent('pointermove', { bubbles: true, ...at(days[5]) }));
        })()
    JS);

    expect((int) $page->script('document.querySelectorAll(".ycal-day.is-pick").length'))->toBeGreaterThan(5);
    $page->screenshot(filename: 'catalog-holidays-bridge-drag');

    $page->script("window.dispatchEvent(new PointerEvent('pointerup', { bubbles: true }))");
    $page->wait(0.6)->assertSee('Marcar 6 días como feriado puente')->screenshot(filename: 'catalog-holidays-bridge-range-confirm');

    expect((int) $page->script('document.querySelectorAll(".ycal-day.is-pick").length'))->toBe(0)
        ->and((int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length'))->toBe($before);

    $page->click('Marcar los días')->wait(1.2)->assertSee('¿Avisar a 1 negocio de')->screenshot(filename: 'catalog-holidays-bridge-notify');

    expect((int) $page->script('document.querySelectorAll(".ycal-day.is-holiday").length'))->toBe($before + 6);

    $page->click('Enviar el aviso')->wait(1)->assertSee('Se avisó a 1 negocio')->assertNoJavaScriptErrors();
});

test('the copy panel previews what it would create before writing anything', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Feriados')->wait(0.8)->click('@holiday-copy')->wait(1);

    $page->assertSee('Copiar feriados a otro país')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-holidays-copy');

    // With no country chosen there is nothing to confirm: the button is not drawn.
    expect((int) $page->script('document.querySelectorAll("[data-testid=holiday-copy-confirm]").length'))->toBe(0);
});

test('the form asks only for the date of the chosen shape', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('Feriados')->wait(0.8)->click('Crear feriado')->wait(1);

    // A new holiday opens as "every year": month and day are asked, the Easter number and the date are not.
    expect((bool) $page->script('document.querySelector("[name=month]").closest(".form-row").offsetParent !== null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("[name=easter_offset]").closest(".form-row").offsetParent === null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("#if-on_date").closest(".form-row").offsetParent === null'))->toBeTrue();

    $page->screenshot(filename: 'catalog-holidays-form-fixed-'.$label);

    // Picking "one year only" swaps the row for the calendar.
    $page->script('$wire = Livewire.all()[Livewire.all().length - 1].$wire; $wire.form.data.kind = "once";');
    $page->wait(0.5);

    expect((bool) $page->script('document.querySelector("#if-on_date").closest(".form-row").offsetParent !== null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("[name=month]").closest(".form-row").offsetParent === null'))->toBeTrue();

    $page->assertNoJavaScriptErrors()->screenshot(filename: 'catalog-holidays-form-once-'.$label);

    // "The first Monday of February": which one, which weekday, which month — and nothing else.
    $page->script('$wire.form.data.kind = "nth";');
    $page->wait(0.5);

    expect((bool) $page->script('document.querySelector("[name=nth]").closest(".form-row").offsetParent !== null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("#if-on_date").closest(".form-row").offsetParent === null'))->toBeTrue();

    $page->assertNoJavaScriptErrors()->screenshot(filename: 'catalog-holidays-form-nth-'.$label);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop' => ['desktop', 1280, false],
    'dark' => ['dark', 1280, true],
    'phone' => ['phone', 390, false],
]);
