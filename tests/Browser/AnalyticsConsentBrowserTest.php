<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The cookie banner of the landing, in the flesh
|--------------------------------------------------------------------------
| Google Analytics may load only after a yes, the choice has to stick and the
| footer link has to bring the question back. The id is a made-up one: only
| the injection of the script is checked, not what Google answers.
*/

const GA_ID = 'G-TEST000000';

/** How many Google tag scripts the page holds right now. */
function gaScripts(): string
{
    return "document.querySelectorAll('script[src*=\"googletagmanager.com\"]').length";
}

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('atendia.analytics.measurement_id', GA_ID);
});

test('the banner asks first and Google loads only after a yes that sticks across a reload', function (): void {
    $page = visit('/')->resize(1280, 900);

    $page->assertSee('Cookies para medir las visitas')->assertNoJavaScriptErrors();

    // Nothing from Google before the answer.
    expect($page->script(gaScripts()))->toBe(0);

    $page->script("document.querySelector('[data-consent-accept]').click()");
    $page->wait(0.5);

    expect($page->script(gaScripts()))->toBe(1)
        ->and($page->script("localStorage.getItem('atendia-cookies')"))->toBe('granted')
        ->and($page->script("document.querySelector('[data-consent]').hidden"))->toBeTrue();

    // The next visit remembers: no banner, and the tag loads by itself.
    $page->script('location.reload()');
    $page->wait(1);

    expect($page->script("document.querySelector('[data-consent]').hidden"))->toBeTrue()
        ->and($page->script(gaScripts()))->toBe(1);
});

test('a no keeps Google out and the footer link brings the question back', function (): void {
    $page = visit('/')->resize(1280, 900);

    $page->script("document.querySelector('[data-consent-reject]').click()");
    $page->wait(0.3);

    expect($page->script(gaScripts()))->toBe(0)
        ->and($page->script("localStorage.getItem('atendia-cookies')"))->toBe('denied')
        ->and($page->script("document.querySelector('[data-consent]').hidden"))->toBeTrue();

    $page->script("document.querySelector('[data-consent-open]').click()");
    $page->wait(0.3);

    expect($page->script("document.querySelector('[data-consent]').hidden"))->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

test('the banner fits a phone and a desktop in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit('/')->resize($width, $height);

    if ($dark) {
        $page->script('document.documentElement.classList.add("dark")');
    }

    $page->assertSee('Cookies para medir las visitas')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'analytics-consent-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

    // The card stays inside the screen on both axes.
    $box = $page->script("(() => { const r = document.querySelector('[data-consent]').getBoundingClientRect(); return [r.left, r.right, r.bottom]; })()");

    expect($box[0])->toBeGreaterThanOrEqual(0)
        ->and($box[1])->toBeLessThanOrEqual($width)
        ->and($box[2])->toBeLessThanOrEqual($height);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
