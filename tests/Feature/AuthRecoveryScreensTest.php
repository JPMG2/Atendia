<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The password and email recovery screens
|--------------------------------------------------------------------------
| They were Breeze's scaffolding (grey text, indigo buttons, its own input)
| next to a login and a register built with the project's own fields. A person
| going from one to the other must not feel they changed product.
*/

/** What Breeze's primitives leave in the page and the project's never do. */
const BREEZE_LEFTOVERS = ['border-gray-300', 'text-gray-600', 'indigo-'];

test('the forgot-password screen uses the project field and button', function (): void {
    $response = $this->get(route('password.request'))
        ->assertOk()
        ->assertSee(__('auth.forgot.heading'))
        ->assertSee(__('auth.forgot.submit'))
        ->assertSee('field-control', false);

    foreach (BREEZE_LEFTOVERS as $leftover) {
        $response->assertDontSee($leftover, false);
    }
});

test('the reset screen asks for the three fields in the project style', function (): void {
    $response = $this->get(route('password.reset', ['token' => 'abc']))
        ->assertOk()
        ->assertSee(__('auth.reset.heading'))
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertSee('name="token"', false)
        ->assertSee('field-control', false);

    foreach (BREEZE_LEFTOVERS as $leftover) {
        $response->assertDontSee($leftover, false);
    }
});

test('the confirm-password screen uses the project field', function (): void {
    $response = $this->actingAs(User::factory()->create())->get(route('password.confirm'))
        ->assertOk()
        ->assertSee(__('auth.confirm.heading'))
        ->assertSee('field-control', false);

    foreach (BREEZE_LEFTOVERS as $leftover) {
        $response->assertDontSee($leftover, false);
    }
});

test('the verify-email screen offers to resend and to sign out with project buttons', function (): void {
    $response = $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))
        ->assertOk()
        ->assertSee(__('auth.verify.heading'))
        ->assertSee(__('auth.verify.resend'))
        ->assertSee(__('auth.verify.logout'));

    foreach (BREEZE_LEFTOVERS as $leftover) {
        $response->assertDontSee($leftover, false);
    }
});

test('the new password is chosen with the same help in the reset and the register screens', function (): void {
    foreach ([route('password.reset', ['token' => 'abc']), route('register')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('pw-meter', false)
            ->assertSee('pw-rules', false)
            ->assertSee(__('auth.password_rules.length'))
            ->assertSee(__('auth.password_rules.upper'))
            ->assertSee(__('auth.password_rules.number'))
            ->assertSee(__('auth.password_rules.symbol'));
    }
});

test('the rules of a new password are copy, not text written into the views', function (): void {
    foreach (['auth/register', 'auth/reset-password', 'components/ui/new-password'] as $view) {
        $source = file_get_contents(resource_path("views/{$view}.blade.php"));

        expect($source)->not->toContain('8 caracteres o más')->not->toContain('Una letra mayúscula');
    }
});

test('the headings speak neutral by default and voseo for Argentina', function (): void {
    $this->withSession(['locale' => 'es'])->get(route('password.request'))->assertSee('Recupera tu contraseña');
    $this->withSession(['locale' => 'es_AR'])->get(route('password.request'))->assertSee('Recuperá tu contraseña');
});

test('a reset link the person has clicked still resets the password through the new form', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Nueva#Clave2026',
        'password_confirmation' => 'Nueva#Clave2026',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('Nueva#Clave2026', $user->fresh()->password))->toBeTrue();
});
