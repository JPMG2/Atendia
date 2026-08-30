<?php

declare(strict_types=1);

use App\Models\User;

test('the login screen renders with the Atendia design system', function (): void {
    $response = $this->get('/login');

    $response->assertStatus(200)
        // Brand and copy in regional Spanish: sentence case, verb up front.
        ->assertSee('Hola de nuevo')
        ->assertSee('Ingresar')
        ->assertSee('¿Olvidaste tu contraseña?')
        // It uses the design system components, not Breeze's raw markup.
        ->assertSee('btn-primary', false)
        ->assertSee('field-input', false)
        // The brand logo points home.
        ->assertSee('Atend', false);
});

test('the login screen has no hardcoded Breeze indigo/gray colors', function (): void {
    $html = $this->get('/login')->getContent();

    // Golden rule: never hardcode a colour, it all comes from tokens.
    expect($html)
        ->not->toContain('text-indigo')
        ->not->toContain('focus:ring-indigo')
        ->not->toContain('text-gray-600')
        ->not->toContain('bg-gray-100');
});

test('the login screen ships the theme toggle for light and dark', function (): void {
    $html = $this->get('/login')->getContent();

    // Non-negotiable: light and dark theme, persisted against the flash.
    expect($html)
        ->toContain('atendia-theme')
        ->toContain('toggleTheme')
        ->toContain('x-show="!dark"')
        ->toContain('x-show="dark"');
});

test('a user can still authenticate from the redesigned login', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('validation errors surface on the redesigned login', function (): void {
    $response = $this->from('/login')->post('/login', [
        'email' => 'nope@example.com',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
