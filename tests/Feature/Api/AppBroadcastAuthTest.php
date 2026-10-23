<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // The pinned null broadcaster answers 200 without running the callback, and
    // channels register on the driver alive at boot: swap it, then reload them.
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
});

/** One call as this person. Sanctum's guard remembers the first person it saw, so each call forgets it. */
function asRealtimeApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

describe('signing a channel subscription', function (): void {
    it('signs the phone\'s own business channel', function (): void {
        $user = payingUser();

        $auth = asRealtimeApp($user)->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-business.'.$user->business_id,
        ])->assertOk()->json('auth');

        expect($auth)->toStartWith(config('broadcasting.connections.reverb.key').':');
    });

    it('refuses another business\'s channel and signs nothing', function (): void {
        $user = payingUser();
        $other = payingUser();

        $response = asRealtimeApp($user)->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-business.'.$other->business_id,
        ])->assertForbidden();

        expect($response->json('auth'))->toBeNull();
    });

    it('refuses any channel that is not a business one', function (): void {
        $user = payingUser();

        asRealtimeApp($user)->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-security.user.'.$user->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('channel_name');
    });

    it('refuses a business channel written any way but the canonical one', function (string $suffix): void {
        $user = payingUser();

        asRealtimeApp($user)->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-business.'.str_replace('{id}', (string) $user->business_id, $suffix),
        ])->assertUnprocessable()->assertJsonValidationErrors('channel_name');
    })->with(['leading zero' => ['0{id}'], 'too long' => ['1234567890123456789']]);

    it('refuses a malformed socket id', function (): void {
        $user = payingUser();

        asRealtimeApp($user)->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => 'not-a-socket',
            'channel_name' => 'private-business.'.$user->business_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('socket_id');
    });

    it('needs a token', function (): void {
        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-business.1',
        ])->assertUnauthorized();
    });
});
