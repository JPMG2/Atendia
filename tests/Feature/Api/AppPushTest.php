<?php

declare(strict_types=1);

use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\LoginDevice;
use App\Models\PanelNotification;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Http::preventStrayRequests();
    // The first matching fake wins, so a test changes the answer here instead of faking again.
    $this->expoAnswer = Http::response(['data' => [['status' => 'ok']]]);
    Http::fake(['exp.host/*' => fn () => $this->expoAnswer]);
});

const PUSH_A = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';
const PUSH_B = 'ExponentPushToken[bbbbbbbbbbbbbbbbbbbbbb]';

/** One call as this person from this install; Sanctum's guard remembers the first person it saw, so each call forgets it. */
function pushApp(User $user, string $deviceId = 'install-0000-aaaa-1111'): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user, $deviceId))->withHeaders(apiDeviceHeaders($deviceId));
}

/** An install of this person that holds a live session and a push address. */
function pushInstall(User $user, string $token, string $deviceId = 'install-0000-aaaa-1111'): LoginDevice
{
    appInstallToken($user, $deviceId);

    $device = $user->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp($deviceId))->firstOrFail();
    $device->registerPushToken($token);

    return $device;
}

function teammateWithPush(User $owner, string $token): User
{
    $teammate = payingUser();
    $teammate->forceFill(['business_id' => $owner->business_id])->save();

    pushInstall($teammate->refresh(), $token, 'install-teammate-0001');

    return $teammate;
}

function raiseNotice(Business $business, string $key = 'waiting:1', bool $revive = true, PanelNotificationType $type = PanelNotificationType::CustomerWaiting): void
{
    app(Tenant::class)->for((int) $business->id, fn () => PanelNotification::raise($business, $type, $key, ['name' => 'Maria Perez'], null, $revive));
}

function pushesSent(): array
{
    return Http::recorded()
        ->flatMap(fn (array $pair): array => $pair[0]->data())
        ->all();
}

describe('registering the address', function (): void {
    it('stores the token on the install that asked and on no other', function (): void {
        $user = payingUser();
        pushInstall($user, PUSH_B, 'install-9999-bbbb-2222');
        appInstallToken($user);

        pushApp($user)->putJson('/api/v1/account/push-token', ['token' => PUSH_A])->assertOk();

        expect($user->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp('install-0000-aaaa-1111'))->value('push_token'))->toBe(PUSH_A)
            ->and($user->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp('install-9999-bbbb-2222'))->value('push_token'))->toBe(PUSH_B);
    });

    it('refuses anything that is not an Expo token', function (string $token): void {
        pushApp(payingUser())->putJson('/api/v1/account/push-token', ['token' => $token])->assertUnprocessable();
    })->with(['plain text', 'ExponentPushToken[]', 'ExponentPushToken[short]', 'FCM:abc123']);

    it('moves the token when the phone changes hands, so it never buzzes the old owner', function (): void {
        $first = payingUser();
        $second = payingUser();
        pushInstall($first, PUSH_A);

        pushApp($second)->putJson('/api/v1/account/push-token', ['token' => PUSH_A])->assertOk();

        expect(LoginDevice::query()->where('push_token', PUSH_A)->pluck('user_id')->all())->toBe([$second->id]);
    });

    it('needs a signed-in install', function (): void {
        $this->putJson('/api/v1/account/push-token', ['token' => PUSH_A], apiDeviceHeaders())->assertUnauthorized();
    });

    it('removes the address on request and on sign-out', function (): void {
        $user = payingUser();
        $device = pushInstall($user, PUSH_A);

        pushApp($user)->deleteJson('/api/v1/account/push-token')->assertOk();
        expect($device->refresh()->push_token)->toBeNull();

        $device->registerPushToken(PUSH_A);
        pushApp($user)->postJson('/api/v1/logout')->assertOk();
        expect($device->refresh()->push_token)->toBeNull();
    });

    it('never shows the token in the device list', function (): void {
        $user = payingUser();
        pushInstall($user, PUSH_A);

        expect(pushApp($user)->getJson('/api/v1/account/devices')->assertOk()->getContent())->not->toContain('PushToken');
    });
});

describe('buzzing the team', function (): void {
    it('pushes a generic line to every member with a live session, with no customer data in it', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        teammateWithPush($owner, PUSH_B);

        raiseNotice($owner->business);

        $sent = pushesSent();
        expect($sent)->toHaveCount(2)
            ->and(collect($sent)->pluck('to')->sort()->values()->all())->toBe([PUSH_A, PUSH_B])
            ->and($sent[0]['body'])->toBe('Hay un aviso nuevo para revisar')
            ->and(json_encode($sent))->not->toContain('Maria')
            ->and($sent[0]['data'])->toBe(['screen' => 'notifications'])
            ->and($sent[0]['priority'])->toBe('high');
    });

    it('carries the unread count for that person as the badge', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);

        raiseNotice($owner->business);

        expect(pushesSent()[0]['badge'])->toBe(1);
    });

    it('never reaches another business', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $stranger = payingUser();
        pushInstall($stranger, PUSH_B, 'install-stranger-0001');

        raiseNotice($owner->business);

        expect(collect(pushesSent())->pluck('to')->all())->toBe([PUSH_A]);
    });

    it('skips a person who muted that kind, and still reaches the rest', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $teammate = teammateWithPush($owner, PUSH_B);
        $teammate->toggleBellType(PanelNotificationType::CustomerWaiting, false);

        raiseNotice($owner->business);

        expect(collect(pushesSent())->pluck('to')->all())->toBe([PUSH_A]);
    });

    it('leaves out an install whose session was killed', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $owner->tokens()->delete();

        raiseNotice($owner->business);

        Http::assertNothingSent();
    });

    it('leaves out an install whose token expired', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $owner->tokens()->update(['expires_at' => now()->subMinute()]);

        raiseNotice($owner->business);

        Http::assertNothingSent();
    });

    it('leaves out an install past the age Sanctum accepts, which is counted from creation', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $owner->tokens()->update(['created_at' => now()->subMinutes(config('sanctum.expiration') + 1)]);

        raiseNotice($owner->business);

        Http::assertNothingSent();
    });

    it('stays quiet for a business the app has closed its door to', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $owner->business->forceFill(['suspended_at' => now()])->save();

        raiseNotice($owner->business);

        Http::assertNothingSent();
    });

    it('does not spend the quiet window when Expo fails, so the next notice still buzzes', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);

        $this->expoAnswer = Http::response('down', 503);
        raiseNotice($owner->business, 'waiting:1');

        $this->expoAnswer = Http::response(['data' => [['status' => 'ok']]]);
        raiseNotice($owner->business, 'waiting:2');

        expect(Http::recorded())->toHaveCount(2);
    });

    it('does not buzz for a notice whose transaction rolled back', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);

        try {
            DB::transaction(function () use ($owner): void {
                raiseNotice($owner->business);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        Http::assertNothingSent();
    });

    it('leaves out a closed account', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);
        $owner->delete();

        raiseNotice($owner->business);

        Http::assertNothingSent();
    });

    it('buzzes once for a burst, then again after the window', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);

        raiseNotice($owner->business, 'waiting:1');
        raiseNotice($owner->business, 'waiting:2');
        expect(pushesSent())->toHaveCount(1);

        $this->travel(config('atendia.app.push.window_seconds') + 1)->seconds();
        raiseNotice($owner->business, 'waiting:3');

        expect(pushesSent())->toHaveCount(2);
    });

    it('stays quiet when a sweep re-reports a fact that did not change', function (): void {
        $owner = payingUser();
        pushInstall($owner, PUSH_A);

        raiseNotice($owner->business, 'waiting:1', revive: false);
        Cache::flush();
        raiseNotice($owner->business, 'waiting:1', revive: false);

        expect(pushesSent())->toHaveCount(1);
    });

    it('drops the address Expo says no longer exists', function (): void {
        $this->expoAnswer = Http::response(['data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]]);
        $owner = payingUser();
        $device = pushInstall($owner, PUSH_A);

        raiseNotice($owner->business);

        expect($device->refresh()->push_token)->toBeNull();
    });

    it('does not break the notice when Expo is down', function (): void {
        $this->expoAnswer = Http::response('down', 503);
        $owner = payingUser();
        $device = pushInstall($owner, PUSH_A);

        raiseNotice($owner->business);

        Http::assertSentCount(1);
        expect(PanelNotification::query()->withoutGlobalScopes()->where('business_id', $owner->business_id)->count())->toBe(1)
            ->and($device->refresh()->push_token)->toBe(PUSH_A);
    });
});
