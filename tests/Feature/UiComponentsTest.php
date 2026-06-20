<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| <x-ui.button>
|--------------------------------------------------------------------------
*/
test('the button renders a <button> with its variant and size classes', function () {
    $html = Blade::render('<x-ui.button variant="primary" size="lg">Go</x-ui.button>');

    expect($html)
        ->toContain('<button')
        ->toContain('type="button"')
        ->toContain('btn')
        ->toContain('btn-primary')
        ->toContain('btn-lg')
        ->toContain('Go');
});

test('the button becomes an <a> when given an href', function () {
    $html = Blade::render('<x-ui.button href="/start">Go</x-ui.button>');

    expect($html)
        ->toContain('<a')
        ->toContain('href="/start"')
        ->not->toContain('<button');
});

test('the button adds w-full when fullWidth is set', function () {
    expect(Blade::render('<x-ui.button :fullWidth="true">Go</x-ui.button>'))->toContain('w-full');
});

test('the button renders an inline icon when given one', function () {
    $html = Blade::render('<x-ui.button icon="zap">Go</x-ui.button>');

    expect($html)->toContain('<svg')->toContain('class="lucide"');
});

test('an invalid button variant falls back to primary instead of a broken class', function () {
    $html = Blade::render('<x-ui.button variant="nope">Go</x-ui.button>');

    expect($html)->toContain('btn-primary')->not->toContain('btn-nope');
});

/*
|--------------------------------------------------------------------------
| <x-ui.badge>
|--------------------------------------------------------------------------
*/
test('the badge renders its variant class and an optional dot', function () {
    $plain = Blade::render('<x-ui.badge variant="accent">New</x-ui.badge>');
    expect($plain)->toContain('badge')->toContain('badge-accent')->toContain('New');

    $withDot = Blade::render('<x-ui.badge dot>New</x-ui.badge>');
    expect($withDot)->toContain('var(--brand)'); // el punto usa el token de marca
});

/*
|--------------------------------------------------------------------------
| <x-ui.card>
|--------------------------------------------------------------------------
*/
test('the card renders the surface class and an interactive variant', function () {
    expect(Blade::render('<x-ui.card>Hi</x-ui.card>'))
        ->toContain('class="card"');

    expect(Blade::render('<x-ui.card interactive>Hi</x-ui.card>'))
        ->toContain('card-interactive');
});

test('the card can render as a custom element', function () {
    expect(Blade::render('<x-ui.card as="article">Hi</x-ui.card>'))
        ->toContain('<article')
        ->toContain('</article>');
});

/*
|--------------------------------------------------------------------------
| <x-ui.icon-button>
|--------------------------------------------------------------------------
*/
test('the icon button renders an accessible button with an inline icon', function () {
    $html = Blade::render('<x-ui.icon-button icon="menu" label="Open menu" />');

    expect($html)
        ->toContain('<button')
        ->toContain('aria-label="Open menu"')
        ->toContain('icon-btn')
        ->toContain('<svg');
});

test('the icon button lets a slot override the default icon', function () {
    $html = Blade::render('<x-ui.icon-button label="Theme"><span>custom</span></x-ui.icon-button>');

    expect($html)->toContain('custom');
});

/*
|--------------------------------------------------------------------------
| Golden rule: theme-aware, no hardcoded colors
|--------------------------------------------------------------------------
*/
test('ui components style themselves through tokens, never hardcoded hex colors', function () {
    // Si no hay hex en el markup, light/dark salen solos desde los tokens de app.css.
    foreach ([
        '<x-ui.button>Go</x-ui.button>',
        '<x-ui.card interactive>Hi</x-ui.card>',
        '<x-ui.icon-button icon="x" label="Close" />',
    ] as $template) {
        expect(Blade::render($template))->not->toContain('#');
    }
});
