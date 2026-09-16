<?php

declare(strict_types=1);

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('every sign-in leaves an activity row, known device or not', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($user->loginActivities()->count())->toBe(2)
        ->and($user->loginDevices()->count())->toBe(1);
});

test('the profile shows the recent activity trail', function (): void {
    $user = User::factory()->create();
    LoginActivity::factory()->for($user)->create([
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36',
        'ip' => '10.0.0.9',
        'location' => 'Buenos Aires, Argentina',
    ]);

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertSee(__('profile.activity.title'))
        ->assertSee('Chrome · Windows')
        ->assertSee('10.0.0.9')
        ->assertSee('Buenos Aires, Argentina');
});

test('activity older than ninety days is pruned on login', function (): void {
    Mail::fake();
    $user = User::factory()->create();
    LoginActivity::factory()->for($user)->create(['created_at' => now()->subDays(120)]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($user->loginActivities()->count())->toBe(1);
});
