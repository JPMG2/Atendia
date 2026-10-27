<?php

declare(strict_types=1);

use App\Actions\Account\RestoreAccount;
use App\Enums\PlatformEventKind;
use App\Enums\SubscriptionStatus;
use App\Mail\AccountClosed;
use App\Mail\AccountPasswordChanged;
use App\Models\LoginDevice;
use App\Models\PlatformEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    RateLimiter::clear('app-account:account:1');
});

/** An owner with a known password, calling from the install the account already used. */
function accountOwner(): User
{
    return payingUser(['password' => Hash::make('Old-secret#2026')]);
}

/**
 * One call with a given token. Sanctum's guard remembers the first person it resolved for the
 * rest of a test, which would make a second token look like the first: forget it every time,
 * or "that token no longer works" can pass for the wrong reason.
 */
function callWith(string $token, string $deviceId = 'install-0000-aaaa-1111'): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->withHeaders(apiDeviceHeaders($deviceId));
}

function asApp(User $user, string $deviceId = 'install-0000-aaaa-1111'): TestCase
{
    return callWith(appInstallToken($user, $deviceId), $deviceId);
}

/** A second install of the same account: its row, and a token that works until the row goes. */
function otherInstall(User $user, string $deviceId = 'install-9999-bbbb-2222'): string
{
    return appInstallToken($user, $deviceId);
}

describe('account info', function (): void {
    it('sends the profile, password and home words the way the web says them', function (): void {
        $profile = asApp(accountOwner())->getJson('/api/v1/me')->assertOk()->json('data.profile');

        expect($profile['name_label'])->toBe(__('settings.profile.name'))
            ->and($profile['member_since_label'])->toBe(__('settings.profile.member_since'))
            ->and($profile['member_since'])->toMatch('/^\d{2}\/\d{2}\/\d{4}$/')
            ->and($profile['password']['sub'])->toBe(__('settings.password.sub'))
            ->and($profile['password']['current'])->toBe(__('settings.current_password'))
            ->and($profile['password']['submit'])->toBe(__('settings.password.submit'))
            ->and($profile['home']['greeting'])->toStartWith('Hola, ')
            ->and($profile['home']['sub'])->toBe(__('client.home.sub_new'))
            ->and($profile['home']['disconnected'])->toBe(__('client.home.disconnected'));
    });

    it('tells the owner they can close the account and which word confirms it', function (): void {
        asApp(accountOwner())->getJson('/api/v1/account')
            ->assertOk()
            ->assertJsonPath('data.can_close', true)
            ->assertJsonPath('data.close_keyword', 'ELIMINAR')
            ->assertJsonPath('data.restore_days', 30);
    });

    it('tells an agent they cannot: only the owner holds the key of the business', function (): void {
        $owner = accountOwner();
        $agent = payingUser(['password' => Hash::make('Old-secret#2026')]);
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);

        asApp($agent->refresh())->getJson('/api/v1/account')->assertOk()->assertJsonPath('data.can_close', false);
    });
});

describe('change password', function (): void {
    it('changes it, mails the receipt and ends every other session but this one', function (): void {
        $user = accountOwner();
        $other = otherInstall($user);

        asApp($user)->postJson('/api/v1/account/password', [
            'current_password' => 'Old-secret#2026',
            'password' => 'New-secret#2027x',
            'password_confirmation' => 'New-secret#2027x',
        ])->assertOk();

        expect(Hash::check('New-secret#2027x', $user->refresh()->password))->toBeTrue();
        Mail::assertQueued(AccountPasswordChanged::class);

        callWith($other, 'install-9999-bbbb-2222')->getJson('/api/v1/me')->assertUnauthorized();
        asApp($user)->getJson('/api/v1/me')->assertOk();
    });

    it('keeps the other sessions when asked to', function (): void {
        $user = accountOwner();
        $other = otherInstall($user);

        asApp($user)->postJson('/api/v1/account/password', [
            'current_password' => 'Old-secret#2026',
            'password' => 'New-secret#2027x',
            'password_confirmation' => 'New-secret#2027x',
            'logout_others' => false,
        ])->assertOk();

        callWith($other, 'install-9999-bbbb-2222')->getJson('/api/v1/me')->assertOk();
    });

    it('refuses a wrong current password without touching the stored one', function (): void {
        $user = accountOwner();

        asApp($user)->postJson('/api/v1/account/password', [
            'current_password' => 'not-it',
            'password' => 'New-secret#2027x',
            'password_confirmation' => 'New-secret#2027x',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        expect(Hash::check('Old-secret#2026', $user->refresh()->password))->toBeTrue();
        Mail::assertNothingQueued();
    });

    it('applies the same policy as the web: weak, unconfirmed and unchanged passwords are refused', function (): void {
        $user = accountOwner();
        $call = fn (array $body) => asApp($user)->postJson('/api/v1/account/password', ['current_password' => 'Old-secret#2026', ...$body]);

        $call(['password' => 'short', 'password_confirmation' => 'short'])->assertJsonValidationErrors('password');
        $call(['password' => 'New-secret#2027x', 'password_confirmation' => 'other'])->assertJsonValidationErrors('password');
        $call(['password' => 'Old-secret#2026', 'password_confirmation' => 'Old-secret#2026'])->assertJsonValidationErrors('password');
    });

    it('stops guessing: the sixth wrong current password is refused as throttled', function (): void {
        $user = accountOwner();
        $token = appInstallToken($user);
        $call = fn () => callWith($token)->postJson('/api/v1/account/password', [
            'current_password' => 'not-it',
            'password' => 'New-secret#2027x',
            'password_confirmation' => 'New-secret#2027x',
        ]);

        foreach (range(1, 5) as $_) {
            $call()->assertUnprocessable();
        }

        // The right password no longer opens the door once the tries ran out.
        callWith($token)->postJson('/api/v1/account/password', [
            'current_password' => 'Old-secret#2026',
            'password' => 'New-secret#2027x',
            'password_confirmation' => 'New-secret#2027x',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        expect(Hash::check('Old-secret#2026', $user->refresh()->password))->toBeTrue();
    });

    it('needs a token', function (): void {
        $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/account/password', [])->assertUnauthorized();
    });
});

describe('connected devices', function (): void {
    it('lists the account devices, freshest first, marking the install in use', function (): void {
        $user = accountOwner();
        otherInstall($user);

        $rows = asApp($user)->getJson('/api/v1/account/devices')->assertOk()->json('data');

        expect($rows)->toHaveCount(2)
            ->and(collect($rows)->where('current', true))->toHaveCount(1)
            ->and($rows[0])->toHaveKeys(['id', 'label', 'location', 'last_login_at', 'current']);
    });

    it('never lists another person devices', function (): void {
        $user = accountOwner();
        $stranger = accountOwner();
        otherInstall($stranger, 'install-7777-cccc-3333');

        $rows = asApp($user)->getJson('/api/v1/account/devices')->assertOk()->json('data');

        expect($rows)->toHaveCount(1);
    });

    it('signs one other install out at once: its token dies and its row is gone', function (): void {
        $user = accountOwner();
        $other = otherInstall($user);
        $row = $user->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp('install-9999-bbbb-2222'))->firstOrFail();

        asApp($user)->deleteJson("/api/v1/account/devices/{$row->id}")->assertOk();

        expect(LoginDevice::find($row->id))->toBeNull();
        callWith($other, 'install-9999-bbbb-2222')->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('refuses to sign out the install in use: that is what logout is for', function (): void {
        $user = accountOwner();
        $token = appInstallToken($user);
        $current = $user->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp('install-0000-aaaa-1111'))->firstOrFail();

        callWith($token)->deleteJson("/api/v1/account/devices/{$current->id}")->assertUnprocessable();

        expect(LoginDevice::find($current->id))->not->toBeNull();
    });

    it('cannot reach the device of another person, even knowing its id', function (): void {
        $user = accountOwner();
        $stranger = accountOwner();
        otherInstall($stranger, 'install-7777-cccc-3333');
        $theirs = $stranger->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp('install-7777-cccc-3333'))->firstOrFail();

        asApp($user)->deleteJson("/api/v1/account/devices/{$theirs->id}")->assertNotFound();

        expect(LoginDevice::find($theirs->id))->not->toBeNull();
    });

    it('ends every other session after the password is confirmed, and only then', function (): void {
        $user = accountOwner();
        $other = otherInstall($user);

        asApp($user)->postJson('/api/v1/account/devices/revoke-others', ['current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        callWith($other, 'install-9999-bbbb-2222')->getJson('/api/v1/me')->assertOk();

        asApp($user)->postJson('/api/v1/account/devices/revoke-others', ['current_password' => 'Old-secret#2026'])->assertOk();

        callWith($other, 'install-9999-bbbb-2222')->getJson('/api/v1/me')->assertUnauthorized();
        asApp($user)->getJson('/api/v1/me')->assertOk();
    });
});

describe('close account', function (): void {
    it('closes the account and its business, ends the token, mails the way back and tells the admin', function (): void {
        $user = accountOwner();
        $token = appInstallToken($user);
        $businessId = $user->business_id;

        callWith($token)->deleteJson('/api/v1/account', [
            'confirmation' => 'ELIMINAR',
            'current_password' => 'Old-secret#2026',
        ])->assertOk();

        expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue()
            ->and($user->business()->withTrashed()->first()->trashed())->toBeTrue()
            ->and(PlatformEvent::where('kind', PlatformEventKind::BusinessCancelled)->where('about_business_id', $businessId)->exists())->toBeTrue();
        Mail::assertQueued(AccountClosed::class);
        callWith($token)->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('is a double lock: the typed word and the password, both', function (): void {
        $user = accountOwner();
        $token = appInstallToken($user);
        $call = fn (array $body) => callWith($token)->deleteJson('/api/v1/account', $body);

        $call(['confirmation' => 'eliminar ya', 'current_password' => 'Old-secret#2026'])->assertJsonValidationErrors('confirmation');
        $call(['confirmation' => 'ELIMINAR', 'current_password' => 'wrong'])->assertJsonValidationErrors('current_password');
        $call(['current_password' => 'Old-secret#2026'])->assertJsonValidationErrors('confirmation');

        expect($user->refresh()->trashed())->toBeFalse();
        Mail::assertNothingQueued();
    });

    it('is not open to an agent: only the owner holds the key of the business', function (): void {
        $owner = accountOwner();
        $agent = payingUser(['password' => Hash::make('Old-secret#2026')]);
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);
        $token = appInstallToken($agent->refresh());

        callWith($token)->deleteJson('/api/v1/account', [
            'confirmation' => 'ELIMINAR',
            'current_password' => 'Old-secret#2026',
        ])->assertForbidden();

        expect($agent->refresh()->trashed())->toBeFalse()->and($owner->business->refresh()->trashed())->toBeFalse();
    });

    it('stays open when the plan has lapsed: leaving is not suspended with the app', function (): void {
        $user = accountOwner();
        $token = appInstallToken($user);
        $user->business->subscription->forceFill(['status' => SubscriptionStatus::Paused, 'current_period_ends_at' => now()->subDays(30)])->save();

        callWith($token)->getJson('/api/v1/account')->assertForbidden();
        callWith($token)->deleteJson('/api/v1/account', [
            'confirmation' => 'ELIMINAR',
            'current_password' => 'Old-secret#2026',
        ])->assertOk();
    });
});

it('does not bring the old tokens back when a closed account is restored', function (): void {
    $user = accountOwner();
    $closing = appInstallToken($user);
    $other = otherInstall($user);

    callWith($closing)->deleteJson('/api/v1/account', ['confirmation' => 'ELIMINAR', 'current_password' => 'Old-secret#2026'])->assertOk();

    $closed = User::withTrashed()->findOrFail($user->id);
    expect(app(RestoreAccount::class)->handle($closed))->toBeTrue();

    callWith($other, 'install-9999-bbbb-2222')->getJson('/api/v1/me')->assertUnauthorized();
    callWith($closing)->getJson('/api/v1/me')->assertUnauthorized();
});

it('shares one set of guesses at the current password across every account action', function (): void {
    $user = accountOwner();
    $token = appInstallToken($user);

    foreach (range(1, 5) as $_) {
        callWith($token)->postJson('/api/v1/account/devices/revoke-others', ['current_password' => 'not-it'])->assertUnprocessable();
    }

    // The other endpoints get no fresh five: even the right password is refused now.
    callWith($token)->postJson('/api/v1/account/password', [
        'current_password' => 'Old-secret#2026',
        'password' => 'New-secret#2027x',
        'password_confirmation' => 'New-secret#2027x',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    callWith($token)->deleteJson('/api/v1/account', ['confirmation' => 'ELIMINAR', 'current_password' => 'Old-secret#2026'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');

    expect($user->refresh()->trashed())->toBeFalse()
        ->and(Hash::check('Old-secret#2026', $user->password))->toBeTrue();
});

it('answers a number too big to be an id with not found, never a server error', function (): void {
    $user = accountOwner();

    asApp($user)->deleteJson('/api/v1/account/devices/99999999999999999999')->assertNotFound();
    asApp($user)->getJson('/api/v1/conversations/99999999999999999999')->assertNotFound();
});

it('keeps the account actions behind the device check: a token shown with another install is refused', function (): void {
    $user = accountOwner();
    $token = appInstallToken($user);

    callWith($token, 'install-9999-bbbb-2222')->postJson('/api/v1/account/password', [
        'current_password' => 'Old-secret#2026',
        'password' => 'New-secret#2027x',
        'password_confirmation' => 'New-secret#2027x',
    ])->assertUnauthorized();

    expect(Hash::check('Old-secret#2026', $user->refresh()->password))->toBeTrue();
});
