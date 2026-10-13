<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/** One failed or right attempt as if it came from the given address. */
function loginFrom(string $address, string $email, string $password = 'wrong-password'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $address])
        ->from('/login')
        ->post('/login', ['email' => $email, 'password' => $password]);
}

test('a visitor cannot choose their address by writing X-Forwarded-For', function (): void {
    Route::get('/_test/ip', fn (Request $request): string => (string) $request->ip());

    // Traefik is a private address and APPENDS the real one at the end.
    $behindTraefik = $this->withServerVariables(['REMOTE_ADDR' => '10.11.0.9'])
        ->withHeaders(['X-Forwarded-For' => '9.9.9.9, 134.199.208.149'])
        ->get('/_test/ip');

    expect($behindTraefik->getContent())->toBe('134.199.208.149');

    // From the open internet nothing it writes is believed.
    $direct = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->withHeaders(['X-Forwarded-For' => '1.1.1.1'])
        ->get('/_test/ip');

    expect($direct->getContent())->toBe('203.0.113.5');
});

test('one account hit from many addresses is locked, whichever the address', function (): void {
    $user = User::factory()->create(['email' => 'victim@example.com']);

    foreach (range(1, 25) as $i) {
        loginFrom('198.51.100.'.$i, 'victim@example.com');
    }

    // The right password, from a fresh address: still refused.
    loginFrom('198.51.100.200', $user->email, 'password')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('one address walking many accounts is locked', function (): void {
    $user = User::factory()->create(['email' => 'someone@example.com']);

    foreach (range(1, 30) as $i) {
        loginFrom('203.0.113.77', "nobody{$i}@example.com");
    }

    // A different, perfectly valid account from that same address.
    loginFrom('203.0.113.77', $user->email, 'password')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a normal person who fails twice and then gets it right is not locked', function (): void {
    $user = User::factory()->create(['email' => 'ok@example.com']);

    loginFrom('192.0.2.10', $user->email);
    loginFrom('192.0.2.10', $user->email);

    loginFrom('192.0.2.10', $user->email, 'password')->assertRedirect();

    $this->assertAuthenticatedAs($user);
});
