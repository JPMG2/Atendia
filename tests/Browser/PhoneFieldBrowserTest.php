<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The dial picker of the phone field, in a real browser
|--------------------------------------------------------------------------
| It was a native select: the browser owned the list and picked an option on
| its own the moment it opened, so nobody could scroll to look for a country.
| The panel is ours now — nothing is chosen until a click or Enter.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);

    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);

    $user = User::factory()->create(['email_verified_at' => now(), 'whatsapp' => null]);
    $user->syncRoles(['support']);
    $this->actingAs($user->refresh());
});

/** Whether the panel is on screen: its placeholder is not page text, so assertSee cannot say. */
function phonePanelOpen($page): bool
{
    // Alpine paints the change a moment after the key or click that caused it.
    $page->wait(0.3);

    return (bool) $page->script('getComputedStyle(document.querySelector(".phone-panel")).display !== "none"');
}

/** A real key press on the search box: keys() would type the word, not press the key. */
function phonePress($page, string $key): void
{
    $page->script("document.querySelector('.phone-search').dispatchEvent(new KeyboardEvent('keydown', {key: '{$key}', bubbles: true}))");
}

test('opening the list chooses nothing, it scrolls, and only a click picks a country', function (): void {
    $page = visit('/admin/seguridad')->resize(1280, 900);

    $dialBefore = $page->script('document.querySelector(".phone-dial .font-mono").innerText');

    expect(phonePanelOpen($page))->toBeFalse();

    $page->click('.phone-dial')->screenshot(filename: 'phone-picker-open');

    expect(phonePanelOpen($page))->toBeTrue();

    // Opening is not choosing: the dial is exactly what it was.
    expect($page->script('document.querySelector(".phone-dial .font-mono").innerText'))->toBe($dialBefore);

    // The list has more rows than fit, and it scrolls under the pointer.
    $scrolled = $page->script(
        'const list = document.querySelector(".phone-list"); list.scrollTop = 9999; list.scrollTop > 0 && list.scrollHeight > list.clientHeight'
    );
    expect($scrolled)->toBeTrue();
    expect($page->script('document.querySelector(".phone-dial .font-mono").innerText'))->toBe($dialBefore);

    $page->click('Uruguay');

    expect(phonePanelOpen($page))->toBeFalse()
        ->and($page->script('document.querySelector(".phone-dial .font-mono").innerText'))->toBe('+598');
});

test('typing narrows the list by name without accents and Enter picks the highlighted one', function (): void {
    $page = visit('/admin/seguridad')->resize(1280, 900);

    $page->click('.phone-dial')->type('.phone-search', 'panama');

    expect($page->script('document.querySelectorAll(".phone-list [role=option]").length'))->toBe(1);

    phonePress($page, 'Enter');

    expect($page->script('document.querySelector(".phone-dial .font-mono").innerText'))->toBe('+507')
        ->and(phonePanelOpen($page))->toBeFalse();
});

test('Escape and a click outside close it without changing the dial, and the number composes with the chosen dial', function (): void {
    $page = visit('/admin/seguridad')->resize(1280, 900);
    $before = $page->script('document.querySelector(".phone-dial .font-mono").innerText');

    $page->click('.phone-dial');
    phonePress($page, 'Escape');
    expect(phonePanelOpen($page))->toBeFalse()
        ->and($page->script('document.querySelector(".phone-dial .font-mono").innerText'))->toBe($before);

    $page->click('.phone-dial')->click('.page-head');
    expect(phonePanelOpen($page))->toBeFalse();

    $page->click('.phone-dial')->click('Uruguay')->type('.phone-number', '99123456');

    expect($page->script('document.querySelector("input[name=whatsapp]").value'))->toBe('+598 99123456');
});

test('a number saved as plain digits shows its own dial and keeps the same digits when saved again', function (): void {
    // On the acting user itself: the browser's server shares that instance, not the row.
    auth()->user()->forceFill(['whatsapp' => '584247673951'])->save();

    $page = visit('/admin/seguridad')->resize(1280, 900)->wait(0.4);
    $page->screenshot(filename: 'phone-saved-number');

    // The dial is read from the digits (longest known prefix), not the catalog's first country (+54 here).
    expect($page->script('document.querySelector(".phone-dial .font-mono").innerText'))->toBe('+58')
        ->and($page->script('document.querySelector(".phone-number").value'))->toBe('4247673951');

    // Editing the number rebuilds the composite on the SAME dial: no doubled prefix.
    $page->type('.phone-number', '4247673950');

    expect(preg_replace('/\D/', '', (string) $page->script('document.querySelector("input[name=whatsapp]").value')))->toBe('584247673950');
});

test('the picker holds at 390px and in the dark theme', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/seguridad')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('.phone-dial')->wait(0.4)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'phone-picker-'.$label);

    expect(phonePanelOpen($page))->toBeTrue();

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

    $panel = $page->script('JSON.stringify(document.querySelector(".phone-panel").getBoundingClientRect())');
    $box = json_decode((string) $panel, true);

    // Inside the screen, never cut at the right edge.
    expect($box['right'])->toBeLessThanOrEqual($width);
})->with([
    'phone' => ['phone', 390, false],
    'dark' => ['dark', 1280, true],
]);
