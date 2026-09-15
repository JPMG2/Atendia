<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| <x-ui.button>
|--------------------------------------------------------------------------
*/
test('the button renders a <button> with its variant and size classes', function (): void {
    $html = Blade::render('<x-ui.button variant="primary" size="lg">Go</x-ui.button>');

    expect($html)
        ->toContain('<button')
        ->toContain('type="button"')
        ->toContain('btn')
        ->toContain('btn-primary')
        ->toContain('btn-lg')
        ->toContain('Go');
});

test('the button becomes an <a> when given an href', function (): void {
    $html = Blade::render('<x-ui.button href="/start">Go</x-ui.button>');

    expect($html)
        ->toContain('<a')
        ->toContain('href="/start"')
        ->not->toContain('<button');
});

test('the button adds w-full when fullWidth is set', function (): void {
    expect(Blade::render('<x-ui.button :fullWidth="true">Go</x-ui.button>'))->toContain('w-full');
});

test('the button renders an inline icon when given one', function (): void {
    $html = Blade::render('<x-ui.button icon="zap">Go</x-ui.button>');

    expect($html)->toContain('<svg')->toContain('class="lucide"');
});

test('an invalid button variant falls back to primary instead of a broken class', function (): void {
    $html = Blade::render('<x-ui.button variant="nope">Go</x-ui.button>');

    expect($html)->toContain('btn-primary')->not->toContain('btn-nope');
});

/*
|--------------------------------------------------------------------------
| <x-ui.badge>
|--------------------------------------------------------------------------
*/
test('the badge renders its variant class and an optional dot', function (): void {
    $plain = Blade::render('<x-ui.badge variant="accent">New</x-ui.badge>');
    expect($plain)->toContain('badge')->toContain('badge-accent')->toContain('New');

    $withDot = Blade::render('<x-ui.badge dot>New</x-ui.badge>');
    expect($withDot)->toContain('var(--brand)'); // the dot uses the brand token

    $neutral = Blade::render('<x-ui.badge variant="neutral" dot>Paused</x-ui.badge>');
    expect($neutral)->toContain('badge-neutral')->toContain('var(--text-subtle)');

    $pulsing = Blade::render('<x-ui.badge dot pulse>Live</x-ui.badge>');
    expect($pulsing)->toContain('badge-dot-pulse');
});

/*
|--------------------------------------------------------------------------
| <x-ui.ai-banner>
|--------------------------------------------------------------------------
*/
test('the ai banner renders its copy, the sparkles icon and the optional action', function (): void {
    $html = Blade::render('<x-ui.ai-banner title="Need a hand?" body="We write it." action="Optimize" />');

    expect($html)
        ->toContain('ai-banner')
        ->toContain('Need a hand?')
        ->toContain('We write it.')
        ->toContain('Optimize')
        ->toContain('<svg');
});

test('the ai banner without an action stays a plain informative block', function (): void {
    expect(Blade::render('<x-ui.ai-banner title="T" body="B" />'))->not->toContain('<button');
});

/*
|--------------------------------------------------------------------------
| <x-ui.card>
|--------------------------------------------------------------------------
*/
test('the card renders the surface class and an interactive variant', function (): void {
    expect(Blade::render('<x-ui.card>Hi</x-ui.card>'))
        ->toContain('class="card"');

    expect(Blade::render('<x-ui.card interactive>Hi</x-ui.card>'))
        ->toContain('card-interactive');
});

test('the card can render as a custom element', function (): void {
    expect(Blade::render('<x-ui.card as="article">Hi</x-ui.card>'))
        ->toContain('<article')
        ->toContain('</article>');
});

/*
|--------------------------------------------------------------------------
| <x-ui.icon-button>
|--------------------------------------------------------------------------
*/
test('the icon button renders an accessible button with an inline icon', function (): void {
    $html = Blade::render('<x-ui.icon-button icon="menu" label="Open menu" />');

    expect($html)
        ->toContain('<button')
        ->toContain('aria-label="Open menu"')
        ->toContain('icon-btn')
        ->toContain('<svg');
});

test('the icon button lets a slot override the default icon', function (): void {
    $html = Blade::render('<x-ui.icon-button label="Theme"><span>custom</span></x-ui.icon-button>');

    expect($html)->toContain('custom');
});

/*
|--------------------------------------------------------------------------
| <x-ui.slide-over>
|--------------------------------------------------------------------------
*/
test('the slide-over renders a dialog panel with title, subtitle and close', function (): void {
    $html = Blade::render('<x-ui.slide-over title="Ficha" subtitle="Detalle">Body</x-ui.slide-over>');

    expect($html)
        ->toContain('slide-over-backdrop')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('Ficha')
        ->toContain('Detalle')
        ->toContain('Body')
        ->toContain(__('dialog.close'));
});

test('the slide-over closes by dispatching one event the caller listens to', function (): void {
    $html = Blade::render('<x-ui.slide-over title="Ficha">Body</x-ui.slide-over>');

    // Escape, backdrop and the X all speak the same event; closing never saves.
    expect(substr_count($html, "\$dispatch('slide-over-close')"))->toBeGreaterThanOrEqual(3);
});

test('the slide-over renders its footer slot when given one', function (): void {
    $html = Blade::render('<x-ui.slide-over title="F"><x-slot:footer>Guardar</x-slot:footer>Body</x-ui.slide-over>');

    expect($html)->toContain('slide-over-foot')->toContain('Guardar');
});

/*
|--------------------------------------------------------------------------
| <x-ui.load-more>
|--------------------------------------------------------------------------
*/
test('the load more footer offers the button and the scroll sentinel together', function (): void {
    $html = Blade::render('<x-ui.load-more :shown="8" :total="22" action="loadMore" />');

    expect($html)
        ->toContain(__('pagination.showing', ['shown' => 8, 'total' => 22]))
        ->toContain(__('pagination.load_more'))
        ->toContain('wire:click.preserve-scroll="loadMore"')
        ->toContain('wire:intersect');
});

test('the load more footer disappears once everything is on screen', function (): void {
    expect(trim(Blade::render('<x-ui.load-more :shown="22" :total="22" action="loadMore" />')))->toBe('');
});

/*
|--------------------------------------------------------------------------
| <x-ui.match>
|--------------------------------------------------------------------------
*/
test('the match component marks the hit, folding accents on both sides', function (): void {
    $html = Blade::render('<x-ui.match text="Coloración" needle="oracion" />');

    expect($html)->toContain('class="match-hit">oración</span');
});

test('the match component stays quiet under three characters or without a hit', function (): void {
    expect(Blade::render('<x-ui.match text="Coloración" needle="co" />'))->not->toContain('match-hit')
        ->and(Blade::render('<x-ui.match text="Coloración" needle="zzz" />'))->not->toContain('match-hit');
});

/*
|--------------------------------------------------------------------------
| Golden rule: theme-aware, no hardcoded colors
|--------------------------------------------------------------------------
*/
test('ui components style themselves through tokens, never hardcoded hex colors', function (): void {
    // With no hex in the markup, light and dark come out of the tokens on their own.
    foreach ([
        '<x-ui.button>Go</x-ui.button>',
        '<x-ui.card interactive>Hi</x-ui.card>',
        '<x-ui.icon-button icon="x" label="Close" />',
        '<x-ui.ai-banner title="T" body="B" action="Go" />',
    ] as $template) {
        expect(Blade::render($template))->not->toContain('#');
    }
});
