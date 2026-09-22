<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

test('the loss headline and the faq accordion, in the flesh', function (): void {
    config()->set('atendia.sales_whatsapp', '5492995529100');

    $page = visit('/');

    $page->assertSee('Nunca más pierdas un cliente')
        ->screenshotElement('#top', 'landing-hero-loss');

    // Closed by default; the click unfolds ONE answer.
    $page->assertDontSee('nunca inventa')
        ->click('¿Puede decirle algo equivocado a mis clientes?')
        ->assertSee('nunca inventa')
        ->assertSee(__('landing.faq.more_cta'))
        ->screenshotElement('#preguntas', 'landing-faq-open');
});

test('the features bento fills its last tile too', function (): void {
    // The brand tile used to ship without a vignette and sat visibly emptier
    // than the two tiles sharing its row.
    $page = visit('/')->resize(560, 900);
    $page->script('document.documentElement.classList.add("dark")');

    $page->assertSee(__('landing.features.brand.title'))
        ->assertSee(__('landing.features.vignettes.brand_badge'))
        ->screenshotElement('#funciones', 'landing-features-bento-dark-mobile');
});

test('the sticky bar never covers a section title, and marks the one being read', function (): void {
    $page = visit('/')->resize(1280, 900);

    // Measured on the reserved room, not on a scroll: free of the smooth
    // scrolling's timing, and true for every anchor on the page.
    $slack = $page->script(
        'const bar = document.querySelector("header").getBoundingClientRect().height;'
        .'Math.min(...[...document.querySelectorAll("main section[id]")]'
        .'  .map(s => parseFloat(getComputedStyle(s).scrollMarginTop) - bar))'
    );

    expect($slack)->toBeGreaterThan(0);

    // Two calls on purpose: the round trip between them lets the scroll event
    // fire and Alpine flush, with no sleep to tune.
    $page->script(
        'window.scrollTo({ top: document.getElementById("precios").offsetTop + 20, behavior: "instant" });'
        .'window.dispatchEvent(new Event("scroll"))'
    );

    expect($page->script('document.querySelector(".nav-link-active").textContent.trim()'))
        ->toBe(__('landing.nav.pricing'));

    $travelled = (float) $page->script(
        'parseFloat(document.querySelector(".nav-progress").style.transform.match(/[\d.]+/)[0])'
    );

    expect($travelled)->toBeGreaterThan(0.0)->toBeLessThan(1.0);

    // The real anchor path now, with the smooth scroll off so it settles at once.
    $page->script('document.documentElement.style.scrollBehavior = "auto"');
    $page->click(__('landing.nav.how'));

    $headroom = (float) $page->script(
        'document.getElementById("como-funciona").getBoundingClientRect().top'
        .'- document.querySelector("header").getBoundingClientRect().height'
    );

    expect($headroom)->toBeGreaterThan(0.0);
});
