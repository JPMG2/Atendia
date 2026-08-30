<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    test()->seed(RolesAndPermissionsSeeder::class);

    // Two traps, either one alone making this file test nothing: the pinned null
    // broadcaster answers 200 without running the callback, and channels register
    // on the driver alive at boot. Hence the reload below.
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
});

/** Ask the real /broadcasting/auth endpoint, which is the path the browser takes. */
function joinChannel(User $user, int $businessId): TestResponse
{
    return test()->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-business.{$businessId}",
    ]);
}

test('a client can only join the channel of its own business', function (): void {
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();

    $user = User::factory()->create(['business_id' => $mine->id]);
    $user->syncRoles('client');

    joinChannel($user, $mine->id)->assertOk();
    joinChannel($user, $theirs->id)->assertForbidden();
});

test('the owner can join any business channel, which is what lets it give support', function (): void {
    $business = Business::factory()->create();

    $admin = User::factory()->create(['business_id' => null]);
    $admin->syncRoles('admin');

    joinChannel($admin, $business->id)->assertOk();
});

test('a user with no business and no admin role is turned away', function (): void {
    // Channel callbacks are NOT gates: the Gate::before super-admin shortcut does
    // not run here, so "no business" alone must never be enough.
    $business = Business::factory()->create();

    $stray = User::factory()->create(['business_id' => null]);
    $stray->syncRoles('client');

    joinChannel($stray, $business->id)->assertForbidden();
});
