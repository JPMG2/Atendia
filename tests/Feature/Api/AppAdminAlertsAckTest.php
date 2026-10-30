<?php

declare(strict_types=1);

use App\Enums\PlatformEventState;
use App\Mail\DeviceChallengeCode;
use App\Models\PlatformEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Marcar visto" from the app
|--------------------------------------------------------------------------
| The web's button: offered and accepted only with its key, marked by the
| model's own `acknowledgeById`, and a second look changes nothing.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function ackToken(string $role = 'admin'): string
{
    Mail::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->syncRoles([$role]);

    $id = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
        ->assertStatus(202)->json('challenge.id');

    $code = '';
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => $code])->assertOk()->json('token');
}

function ackCall(string $token, string $method, string $path): TestResponse
{
    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->json($method, '/api/v1/admin/'.$path);
}

test('an open alarm is marked seen with the web\'s action, and the board then says who saw it', function (): void {
    $event = PlatformEvent::factory()->create(['state' => PlatformEventState::Open]);
    $token = ackToken();

    expect(ackCall($token, 'GET', 'alerts')->assertOk()->json('data.events.ack'))->toBe(__('platform_events.screen.ack'));

    ackCall($token, 'POST', "alerts/{$event->id}/acknowledge")->assertOk()->assertJsonPath('data.state', 'acknowledged');

    $row = collect(ackCall($token, 'GET', 'alerts')->json('data.events.rows'))->firstWhere('id', $event->id);
    expect($event->refresh()->state)->toBe(PlatformEventState::Acknowledged)
        ->and($row['state'])->toBe('acknowledged')
        ->and($row['seen_by'])->not->toBeNull();
});

test('a second look, or one at a resolved alarm, changes nothing, as on the web', function (): void {
    $seen = PlatformEvent::factory()->create(['state' => PlatformEventState::Open]);
    $resolved = PlatformEvent::factory()->create(['state' => PlatformEventState::Resolved]);
    $token = ackToken();

    ackCall($token, 'POST', "alerts/{$seen->id}/acknowledge")->assertOk();
    $first = $seen->refresh()->acknowledged_at;
    $this->travel(5)->minutes();
    ackCall($token, 'POST', "alerts/{$seen->id}/acknowledge")->assertOk();

    expect($seen->refresh()->acknowledged_at->equalTo($first))->toBeTrue();
    ackCall($token, 'POST', "alerts/{$resolved->id}/acknowledge")->assertOk()->assertJsonPath('data.state', 'resolved');
});

test('who may mark but not look at the board is refused too, as on the web', function (): void {
    Role::findOrCreate('marker', 'web')->syncPermissions(['access-admin-panel', 'alerts.acknowledge']);
    $event = PlatformEvent::factory()->create(['state' => PlatformEventState::Open]);

    ackCall(ackToken('marker'), 'POST', "alerts/{$event->id}/acknowledge")->assertForbidden();
    expect($event->refresh()->state)->toBe(PlatformEventState::Open);
});

test('who may look at the board but not mark it gets no button and is refused', function (): void {
    Role::findOrCreate('watcher', 'web')->syncPermissions(['access-admin-panel', 'alerts.view']);
    $event = PlatformEvent::factory()->create(['state' => PlatformEventState::Open]);
    $token = ackToken('watcher');

    expect(ackCall($token, 'GET', 'alerts')->assertOk()->json('data.events.ack'))->toBeNull();
    ackCall($token, 'POST', "alerts/{$event->id}/acknowledge")->assertForbidden();
    expect($event->refresh()->state)->toBe(PlatformEventState::Open);
});
