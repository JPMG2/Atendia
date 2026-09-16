<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
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

    public function test_the_register_button_actually_submits_the_form(): void
    {
        // x-ui.button defaults to type="button" (right for wire:click); the
        // owner hit a dead form when this one shipped without the explicit
        // submit. Caught live on 2026-09-16.
        $this->get('/register')->assertSee('type="submit"', false);
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
            'password' => 'Segura#2026',
            'password_confirmation' => 'Segura#2026',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('onboarding', absolute: false));
    }

    public function test_a_password_missing_any_required_class_is_rejected(): void
    {
        // The owner's policy of 2026-09-16: min 8 with an uppercase letter,
        // a number and a symbol — her own '12345678' must bounce now.
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['12345678', 'Segura#x', 'seguras#2026', 'Segura2026'] as $weak) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => 'debil@example.com',
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->assertGuest();
    }

    public function test_the_password_field_teaches_the_pattern_live(): void
    {
        // The rules teach by checking themselves off while the user types;
        // the guard mirrors them again on submit.
        $this->get('/register')
            ->assertSee('8 caracteres o más')
            ->assertSee('Una letra mayúscula')
            ->assertSee('Un número')
            ->assertSee('Un carácter especial (ej. ! @ #)')
            ->assertSee('pw-rules', false)
            ->assertSee('hasUpper', false)
            ->assertSee('hasSymbol', false)
            // Both password fields ship the peek toggle.
            ->assertSee('field-peek', false);
    }

    public function test_a_password_seen_in_a_data_leak_is_rejected(): void
    {
        // The base TestCase lets every password through so the suite stays
        // off the network; here the verifier flags them all, proving the
        // uncompromised() gate actually bites.
        $this->app->bind(UncompromisedVerifier::class, fn (): UncompromisedVerifier => new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return false;
            }
        });

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'filtrada@example.com',
            'password' => 'Segura#2026',
            'password_confirmation' => 'Segura#2026',
        ])->assertSessionHasErrors(['password' => __('validation.password.uncompromised')]);

        $this->assertGuest();
    }

    public function test_a_typoed_email_domain_gets_a_suggestion(): void
    {
        // Mailcheck pattern: a slip like gmial.com would swallow every mail
        // the app sends later; the page offers the fix on blur.
        $this->get('/register')
            ->assertSee('¿Quisiste decir')
            ->assertSee('suggestEmail', false)
            ->assertSee('field-suggest', false);
    }

    public function test_the_strength_meter_grows_with_the_checklist(): void
    {
        // One score feeds both the meter and the checklist, so they can
        // never disagree about how strong the password looks.
        $this->get('/register')
            ->assertSee('pw-meter', false)
            ->assertSee('pwStrength', false)
            ->assertSee('pwScore', false)
            ->assertSee('Débil')
            ->assertSee('Fuerte');
    }
}
