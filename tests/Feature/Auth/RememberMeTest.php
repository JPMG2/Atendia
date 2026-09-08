<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logging in with remember me issues the persistent remember cookie', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $this->assertAuthenticated();
    // The recaller cookie is what re-authenticates the user after the
    // server session expires; without it "remember me" is a dead checkbox.
    $response->assertCookie(auth()->guard('web')->getRecallerName());
    expect($user->fresh()->remember_token)->not->toBeNull();
});
