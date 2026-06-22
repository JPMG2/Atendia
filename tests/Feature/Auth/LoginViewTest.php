<?php

use App\Models\User;

test('the login screen renders with the Atendia design system', function (): void {
    $response = $this->get('/login');

    $response->assertStatus(200)
        // Marca y copy en español rioplatense (sentence case, verbo cercano)
        ->assertSee('Hola de nuevo')
        ->assertSee('Ingresar')
        ->assertSee('¿Olvidaste tu contraseña?')
        // Usa los componentes del design system, no el markup crudo de Breeze
        ->assertSee('btn-primary', false)
        ->assertSee('field-input', false)
        // El logo de marca apunta al home
        ->assertSee('Atend', false);
});

test('the login screen has no hardcoded Breeze indigo/gray colors', function (): void {
    $html = $this->get('/login')->getContent();

    // Regla de oro: jamás hardcodear color; todo sale de tokens (jade + coral)
    expect($html)
        ->not->toContain('text-indigo')
        ->not->toContain('focus:ring-indigo')
        ->not->toContain('text-gray-600')
        ->not->toContain('bg-gray-100');
});

test('the login screen ships the theme toggle for light and dark', function (): void {
    $html = $this->get('/login')->getContent();

    // Mandato no negociable: tema claro/oscuro con persistencia anti-flash
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
