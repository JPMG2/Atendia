<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// It logs a user in: without this the user, its device and its login stayed in the test database.
uses(RefreshDatabase::class);

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
        ->toContain('x-show="! dark"')
        ->toContain('x-show="dark"');
});

test('an idle close lands as a warning, never as a success', function (): void {
    $html = $this->get('/login?idle=1')->getContent();

    expect($html)
        ->toContain('Cerramos tu sesión por inactividad')
        ->toContain('alert-warning')
        ->not->toContain('alert-success');

    // Without the flag the login carries no notice at all.
    expect($this->get('/login')->getContent())->not->toContain('Cerramos tu sesión por inactividad');
});

test('a dead session that is not the idle close says it expired', function (): void {
    $this->get('/login?expired=1')
        ->assertOk()
        ->assertSee(__('errors.expired_alert'))
        ->assertDontSee(__('auth.staff_idle'));
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
