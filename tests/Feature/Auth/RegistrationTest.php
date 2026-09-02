<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_the_register_form_guards_on_the_front_like_the_catalogs_do(): void
    {
        // `novalidate` kills the browser's native bubbles (the one dialog
        // nobody can theme); the Alpine guard speaks for the form instead.
        $this->get('/register')
            ->assertSee('novalidate', false)
            ->assertSee('x-data="registerGuard"', false)
            ->assertSee("['minLength', 8]", false)
            ->assertSee("['same', values.password]", false)
            ->assertSee('errors.password_confirmation', false)
            ->assertSee('field-control', false);
    }

    public function test_the_registration_screen_speaks_spanish(): void
    {
        // The stock Breeze keys shipped untranslated. Positive assertions:
        // DontSee('Register') would trip on RegisteredUserController leaking
        // into framework metadata, not on copy.
        $this->get('/register')
            ->assertSee('Crear mi cuenta')
            ->assertSee('Contraseña')
            ->assertSee('¿Ya creaste tu cuenta?')
            ->assertDontSee('Already registered?');
    }

    public function test_new_users_can_register(): void
    {
        // Signing up assigns the client role by default, so the role has to exist.
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
