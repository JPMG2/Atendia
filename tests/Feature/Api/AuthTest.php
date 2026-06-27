<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

// La base de testing está pre-migrada y no usa RefreshDatabase global;
// envolvemos cada test en una transacción para aislar los datos.
uses(DatabaseTransactions::class);

it('registra un usuario y devuelve un token de acceso', function (): void {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'iphone-de-ana',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token'])
        ->assertJsonPath('data.email', 'ana@example.com');

    expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
});

it('rechaza el registro con email duplicado', function (): void {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->postJson('/api/v1/register', [
        'name' => 'Otro',
        'email' => 'dup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('hace login con credenciales válidas y devuelve un token', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'secret123',
        'device_name' => 'android',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['id', 'email'], 'token'])
        ->assertJsonPath('data.email', $user->email);
});

it('rechaza el login con credenciales inválidas', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'incorrecta',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('me devuelve el usuario autenticado', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('me rechaza la petición sin token', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('logout revoca únicamente el token usado en la petición', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('app')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
