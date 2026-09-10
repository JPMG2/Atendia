<?php

declare(strict_types=1);

test('the landing page loads successfully', function (): void {
    $this->get('/')->assertOk();
});

test('it shows the Atendia brand and hero', function (): void {
    $this->get('/')
        ->assertSee('atendido por IA', false)
        ->assertSee('Crear mi asistente', false)
        ->assertSee('Empezar gratis', false);
});

test('it renders every marketing section', function (): void {
    $response = $this->get('/');

    foreach (['funciones', 'como-funciona', 'casos', 'precios', 'clientes'] as $id) {
        $response->assertSee('id="'.$id.'"', false);
    }
});

test('the hero phone wears the house bezel and the page enters animated', function (): void {
    $this->get('/')
        // Surface bezel + entrance sequence; the near-black frame that
        // swallowed the hero must never come back.
        ->assertSee('hero-phone-enter', false)
        ->assertSee('hero-enter-5', false)
        ->assertSee('phone-bubble-4', false)
        ->assertDontSee('background: var(--ink-900)', false);
});

test('the hero keeps breathing: typed headline, live clock and a chat pool', function (): void {
    $this->get('/')
        ->assertSee('data-hero-type', false)
        ->assertSee('data-hero-clock', false)
        ->assertSee('data-live-pool', false)
        // One live exchange straight from the translations.
        ->assertSee(__('landing.phone.b5'))
        ->assertSee('5G', false);
});

test('it supports dark mode and is not a React prototype', function (): void {
    $this->get('/')
        // The theme toggle persists in localStorage under the project key.
        ->assertSee('atendia-theme', false)
        // Professional, not mediocre: no compiling React/Babel in the browser.
        ->assertDontSee('babel/standalone', false)
        ->assertDontSee('react-dom', false);
});

test('it renders icons as inline SVG, not through a JS CDN', function (): void {
    $this->get('/')
        ->assertSee('<svg', false)
        ->assertSee('class="lucide"', false)
        // No client-side icon library anymore: self-hosted inline SVG only.
        ->assertDontSee('data-lucide', false)
        ->assertDontSee('lucide.createIcons', false)
        ->assertDontSee('unpkg.com/lucide', false);
});
