<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

// The testing database is pre-migrated and does not use a global
// RefreshDatabase, so each test is wrapped in a transaction.
uses(DatabaseTransactions::class);

it('has no sign-up: the app is only for customers who already bought a plan', function (): void {
    $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'Password#123',
        'password_confirmation' => 'Password#123',
    ])->assertNotFound();

    expect(User::where('email', 'ana@example.com')->exists())->toBeFalse();
});

it('hace login con credenciales válidas y devuelve un token', function (): void {
    $user = payingUser(['password' => Hash::make('secret123')]);

    $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'secret123',
        'device_name' => 'android',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['id', 'email'], 'token'])
        ->assertJsonPath('data.email', $user->email);
});

it('rechaza el login con credenciales inválidas', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'incorrecta',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('me devuelve el usuario autenticado', function (): void {
    $user = payingUser();
    $token = appInstallToken($user);

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('me rechaza la petición sin token', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('logout revoca únicamente el token usado en la petición', function (): void {
    $user = payingUser();
    $token = appInstallToken($user);

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->postJson('/api/v1/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('un token sirve a los 59 días y deja de servir a los 61', function (): void {
    $token = appInstallToken(payingUser());

    $this->travel(59)->days();
    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertOk();

    // The guard keeps the user it resolved on the first request; a fresh one checks the token again.
    app('auth')->forgetGuards();
    $this->travel(2)->days();
    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertUnauthorized();
});

test('an email sent as a list is a validation error, never a crash', function (): void {
    $this->postJson('/api/v1/login', ['email' => ['a@b.test'], 'password' => 'secret'])
        ->assertUnprocessable();
});
