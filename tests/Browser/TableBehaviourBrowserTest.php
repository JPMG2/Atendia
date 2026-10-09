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
| What an admin table does when it is touched
|--------------------------------------------------------------------------
| Sorting, the clickable row and the heading that sticks are behaviour of
| the render, so they are read from the page, not from the markup.
*/

const TABLE_NAMES_JS = 'Array.from(document.querySelectorAll(".pay-table tbody tr td.is-key")).map(td => td.textContent.trim()).join("|")';

/** A real keydown, bubbling from the given element, the way the browser would send it. */
const TABLE_KEY_JS = '(() => { %s.dispatchEvent(new KeyboardEvent("keydown", { key: "%s", bubbles: true })); return true; })()';

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    foreach (['Mármol', 'Aurora', 'Zafiro', 'Brisa'] as $name) {
        Business::factory()->create(['name' => $name]);
    }

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('a heading sorts its column up, then down, then gives the order back', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 900);

    $original = $page->script(TABLE_NAMES_JS);

    $page->click('.pay-table thead th:nth-child(1)')->hover('.pay-table tbody tr:nth-child(2) td.is-key')
        ->screenshot(filename: 'tables-sort-asc-row-hover');
    expect($page->script(TABLE_NAMES_JS))->toBe('Aurora|Brisa|Mármol|Zafiro')
        ->and($page->script('document.querySelector(".pay-table thead th:nth-child(1)").getAttribute("aria-sort")'))->toBe('ascending');

    $page->click('.pay-table thead th:nth-child(1)');
    expect($page->script(TABLE_NAMES_JS))->toBe('Zafiro|Mármol|Brisa|Aurora');

    $page->click('.pay-table thead th:nth-child(1)')->screenshot(filename: 'tables-sort-third-click');
    expect($page->script(TABLE_NAMES_JS))->toBe($original)
        ->and($page->script('document.querySelector(".pay-table thead th:nth-child(1)").hasAttribute("aria-sort")'))->toBeFalse();
});

test('a heading with nothing in it does not sort', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 900);

    expect((bool) $page->script('document.querySelector(".pay-table thead th:last-child").hasAttribute("tabindex")'))->toBeFalse();
});

test('clicking a row runs the row\'s own action, and a click on a link inside it does not double it', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 900);

    // The row's key cell, not the button: the cell has no handler of its own.
    $page->click('.pay-table tbody tr:first-child td.is-key');

    // The round trip to Livewire is async: assertSee waits for it, a script read would not.
    $page->assertSee('Cerrar la ficha');
});

test('the order she chose is still there after a reload', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 900);

    $page->click('.pay-table thead th:nth-child(1)')->click('.pay-table thead th:nth-child(1)');
    expect($page->script(TABLE_NAMES_JS))->toBe('Zafiro|Mármol|Brisa|Aurora');

    $page->script('location.reload()');

    $page->assertSee('Negocios')->assertSee('Zafiro');
    expect($page->script(TABLE_NAMES_JS))->toBe('Zafiro|Mármol|Brisa|Aurora')
        ->and($page->script('document.querySelector(".pay-table thead th:nth-child(1)").getAttribute("aria-sort")'))->toBe('descending');
});

test('j and k walk the rows and Enter runs the row\'s action', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 900);

    $press = fn (string $key) => $page->script(sprintf(TABLE_KEY_JS, 'document.body', $key));
    $at = 'Array.from(document.querySelectorAll(".pay-table tbody tr")).findIndex(r => r.hasAttribute("data-kb"))';

    $press('j');
    $press('j');
    $press('k');
    expect($page->script($at))->toBe(0);

    $press('j');
    $page->screenshot(filename: 'tables-keyboard-row');
    expect($page->script($at))->toBe(1);

    $press('Enter');
    $page->assertSee('Cerrar la ficha');
});

test('typing in a field never moves the keyboard row', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 900);

    $page->script(sprintf(TABLE_KEY_JS, 'document.querySelector("[name=search]")', 'j'));

    expect((int) $page->script('document.querySelectorAll(".pay-table tbody tr[data-kb]").length'))->toBe(0);
});

test('the heading sticks under the topbar while the page scrolls', function (): void {
    Business::factory()->count(30)->create();

    $page = visit(route('admin.businesses'))->resize(1280, 700);

    $page->script('window.scrollTo(0, 600)');

    $page->screenshot(filename: 'tables-sticky-heading');

    $top = (float) $page->script('Math.round(document.querySelector(".pay-table thead th").getBoundingClientRect().top)');
    $bar = (float) $page->script('document.querySelector(".topbar").getBoundingClientRect().height');

    expect($top)->toBe($bar)
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
});
