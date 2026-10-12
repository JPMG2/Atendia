<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
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

test('a signed-in user who opens the login screen is sent to their own panel', function (string $panel): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = signInOnPanel($panel);

    // The guest middleware bounces a signed-in user off /login; it has to land
    // on their panel: an admin was dropped on the client dashboard (2026-10-12).
    $this->get('/login')->assertRedirect($user->panelHome());
})->with(['admin', 'client']);
