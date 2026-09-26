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

    // The catalog is the second star tile, mirrored under the panel.
    $page->assertSee(__('landing.features.vignettes.catalog_handoff'))
        ->screenshotElement('#funciones', 'landing-features-bento');

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

test('the bar shrinks, warms its cta past the price and marks the drawer', function (): void {
    $page = visit('/')->resize(1280, 900);

    // Transitions off: the computed value settles at once, with no sleep to tune.
    $page->script(
        'const s = document.createElement("style");'
        .'s.textContent = "*{transition:none !important}";'
        .'document.head.appendChild(s)'
    );

    $toPricing = fn () => $page->script(
        'window.scrollTo({ top: document.getElementById("precios").offsetTop + 20, behavior: "instant" });'
        .'window.dispatchEvent(new Event("scroll"))'
    );

    expect($page->script('getComputedStyle(document.querySelector(".navbar-row")).paddingTop'))->toBe('12px');

    $toPricing();

    expect($page->script('getComputedStyle(document.querySelector(".navbar-row")).paddingTop'))->toBe('6px');

    $page->screenshotElement('header', 'landing-navbar-compact');

    // The coral wins over the jade because .btn-accent is painted later.
    $cta = $page->script(
        'const el = document.querySelector(".navbar-row .btn-primary");'
        .'[el.classList.contains("btn-accent"), getComputedStyle(el).backgroundColor]'
    );

    expect($cta[0])->toBeTrue()
        ->and($cta[1])->not->toBe($page->script(
            'getComputedStyle(document.querySelector(".navbar-row .btn-secondary, .navbar-row .btn-ghost")).backgroundColor'
        ));

    // The drawer says where the reader is, same as the desktop menu.
    $page->resize(560, 900);
    $toPricing();
    $page->click('[aria-label="'.__('landing.nav.open_menu').'"]');

    expect($page->script('document.querySelectorAll(".nav-link-active").length'))->toBe(2);
});
