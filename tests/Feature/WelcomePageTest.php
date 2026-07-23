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
