<?php

declare(strict_types=1);

use App\Mail\DeviceChallengeCode;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Admin in the app: the way in
|--------------------------------------------------------------------------
| The platform's people hold the keys to every business, so the app asks them
| for the second step on EVERY sign-in, ends their session after the web's idle
| minutes, and keeps their token apart from the customers'. The lock is on the
| server: nothing here depends on a menu row being hidden.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('atendia.security.staff_idle_minutes', 20);
});

function staffMember(string $role = 'admin'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->syncRoles([$role]);

    return $user->refresh();
}

function staffAppLogin(User $user, string $deviceId = 'install-0000-aaaa-1111'): TestResponse
{
    return test()->withHeaders(apiDeviceHeaders($deviceId))->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ]);
}

function staffAppCode(): string
{
    $code = '';

    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

/** Signs in the way the app does: password, then the code the mail carried. Returns the staff token. */
function staffInApp(User $user, string $deviceId = 'install-0000-aaaa-1111'): string
{
    Mail::fake();
    $id = staffAppLogin($user, $deviceId)->assertStatus(202)->json('challenge.id');

    return test()->withHeaders(apiDeviceHeaders($deviceId))->postJson('/api/v1/login/challenge', [
        'challenge_id' => $id,
        'code' => staffAppCode(),
    ])->assertOk()->json('token');
}

function asStaffToken(string $token, array $headers = []): TestResponse
{
    return test()->withToken($token)->withHeaders([...apiDeviceHeaders(), ...$headers])->getJson('/api/v1/admin/me');
}

test('staff are asked for a code on every sign-in, even from an install they already use, and get no token before it', function (): void {
    Mail::fake();
    $admin = staffMember();
    $token = staffInApp($admin);

    // Known install now: a customer would walk straight in; staff are asked again.
    Mail::fake();
    staffAppLogin($admin)->assertStatus(202)
        ->assertJsonStructure(['challenge' => ['id', 'via_whatsapp', 'destination', 'expires_in']])
        ->assertJsonMissingPath('token');

    Mail::assertQueued(DeviceChallengeCode::class, fn ($mail): bool => $mail->hasTo($admin->email));
    expect($token)->not->toBeEmpty();
});

test('a wrong code opens nothing and the right one gives a token that reaches the admin door', function (): void {
    Mail::fake();
    $admin = staffMember();
    $id = staffAppLogin($admin)->assertStatus(202)->json('challenge.id');

    test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => '000000'])
        ->assertUnprocessable();

    expect($admin->tokens()->count())->toBe(0);

    $token = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', [
        'challenge_id' => $id,
        'code' => staffAppCode(),
    ])->assertOk()->json('token');

    asStaffToken($token)->assertOk()
        ->assertJsonPath('data.email', $admin->email)
        ->assertJsonPath('idle_minutes', 20);

    expect($admin->tokens()->first()->abilities)->toBe(['staff']);
});

test('support staff get in the same way as the admin', function (): void {
    $token = staffInApp(staffMember('support'));

    asStaffToken($token)->assertOk();
});

test('a customer token never opens the admin door', function (): void {
    $owner = payingUser();
    $token = appInstallToken($owner);

    asStaffToken($token)->assertForbidden()->assertJsonPath('code', 'staff_only');
});

test('a token with no staff ability does not open it even for a person who is staff, so an old token needs the second step again', function (): void {
    $admin = staffMember();
    $token = appInstallToken($admin);

    asStaffToken($token)->assertForbidden()->assertJsonPath('code', 'staff_only');
});

test('a person who loses the area key loses the door on the next call', function (): void {
    $admin = staffMember('support');
    $token = staffInApp($admin);

    asStaffToken($token)->assertOk();

    $admin->syncRoles([]);
    $admin->forgetCachedPermissions();

    // A real request is a new process; inside one test the guard would keep serving the user it already loaded.
    app('auth')->forgetGuards();

    // The token does not linger half-valid: it dies, and with it the way to a customer's own acts.
    asStaffToken($token)->assertUnauthorized()->assertJsonPath('code', 'staff_revoked');

    expect($admin->tokens()->count())->toBe(0);
});

test('an account that is staff AND owns a business reaches the admin door and still none of the customer\'s', function (): void {
    $owner = payingUser();
    $owner->assignRole('admin');
    $token = staffInApp($owner->refresh());

    asStaffToken($token)->assertOk();

    foreach (['/api/v1/me', '/api/v1/business', '/api/v1/team', '/api/v1/products'] as $path) {
        test()->withToken($token)->withHeaders(apiDeviceHeaders())->getJson($path)
            ->assertForbidden()->assertJsonPath('code', 'staff_refused');
    }

    test()->withToken($token)->withHeaders(apiDeviceHeaders())->postJson('/api/v1/account/password', [
        'current_password' => 'password',
        'password' => 'Nueva#Clave123',
        'password_confirmation' => 'Nueva#Clave123',
    ])->assertForbidden()->assertJsonPath('code', 'staff_refused');
});

test('past the plazo of the second step the admin door shuts, as the web panel does, until it is turned on', function (): void {
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);
    $admin = staffMember('support');
    $admin->forceFill(['created_at' => '2026-02-01'])->save();
    $token = staffInApp($admin);

    app('auth')->forgetGuards();
    asStaffToken($token)->assertForbidden()
        ->assertJsonPath('code', 'staff_second_step')
        ->assertJsonPath('message', __('security.staff.required'));

    $admin->forceFill(['whatsapp' => '5492995550001', 'two_factor_whatsapp_at' => now()])->save();

    app('auth')->forgetGuards();
    asStaffToken($token)->assertOk();
});

test('a resend brings a new code but not new tries, so the guesses stay at five per sign-in', function (): void {
    Mail::fake();
    $admin = staffMember();
    $id = staffAppLogin($admin)->assertStatus(202)->json('challenge.id');
    $answer = fn (string $code) => test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => $code]);

    foreach (range(1, 5) as $ignored) {
        $answer('000000')->assertUnprocessable();
    }

    test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge/resend', ['challenge_id' => $id])->assertOk();

    // The fresh code is right, but the five tries of this sign-in are spent.
    $answer(staffAppCode())->assertUnprocessable();

    expect($admin->tokens()->count())->toBe(0);
});

test('a resend after a few wrong tries still lets the fresh code in', function (): void {
    Mail::fake();
    $admin = staffMember();
    $id = staffAppLogin($admin)->assertStatus(202)->json('challenge.id');

    test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => '000000'])->assertUnprocessable();
    test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge/resend', ['challenge_id' => $id])->assertOk();

    test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => staffAppCode()])
        ->assertOk()->assertJsonStructure(['token']);
});

test('a staff token reaches nothing that is a customer\'s', function (): void {
    $token = staffInApp(staffMember());

    test()->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')
        ->assertForbidden()->assertJsonPath('code', 'app_access_denied');

    test()->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/business')
        ->assertForbidden();
});

test('a staff token cannot close an account, which would take the admin\'s own', function (): void {
    $admin = staffMember();
    $token = staffInApp($admin);

    test()->withToken($token)->withHeaders(apiDeviceHeaders())->deleteJson('/api/v1/account', [
        'confirmation' => __('settings.close.keyword'),
        'current_password' => 'password',
    ])->assertForbidden()->assertJsonPath('code', 'staff_refused');

    expect($admin->fresh())->not->toBeNull()->and($admin->tokens()->count())->toBe(1);
});

test('a customer still closes their own account through the same door', function (): void {
    Mail::fake();
    $owner = payingUser();
    $token = appInstallToken($owner);

    test()->withToken($token)->withHeaders(apiDeviceHeaders())->deleteJson('/api/v1/account', [
        'confirmation' => __('settings.close.keyword'),
        'current_password' => 'password',
    ])->assertOk();
});

test('the session ends after the idle minutes and the token dies with it', function (): void {
    $admin = staffMember();
    $token = staffInApp($admin);

    $this->travel(19)->minutes();
    asStaffToken($token)->assertOk();

    // The call above was a person: the clock restarted from it.
    $this->travel(19)->minutes();
    asStaffToken($token)->assertOk();

    $this->travel(21)->minutes();
    asStaffToken($token)->assertUnauthorized()->assertJsonPath('code', 'staff_idle');

    expect($admin->tokens()->count())->toBe(0);
});

test('what the app does on its own does not keep the session alive', function (): void {
    $token = staffInApp(staffMember());

    $this->travel(15)->minutes();
    asStaffToken($token, ['X-Passive' => '1'])->assertOk();

    $this->travel(10)->minutes();
    asStaffToken($token)->assertUnauthorized()->assertJsonPath('code', 'staff_idle');
});

test('a clock the cache lost kills the token instead of leaving it open', function (): void {
    $admin = staffMember();
    $token = staffInApp($admin);

    Cache::flush();

    asStaffToken($token)->assertUnauthorized()->assertJsonPath('code', 'staff_idle');
    expect($admin->tokens()->count())->toBe(0);
});

test('the minutes are the web\'s own setting, and zero turns the clock off as on the web', function (): void {
    config()->set('atendia.security.staff_idle_minutes', 0);
    $token = staffInApp(staffMember());

    $this->travel(5)->hours();

    asStaffToken($token)->assertOk();
});

test('a customer\'s token is not subject to the staff clock', function (): void {
    $owner = payingUser();
    $token = appInstallToken($owner);

    $this->travel(3)->hours();

    test()->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertOk();
});

test('staff see every business, as on the web: with no tenant Postgres opens the rows, and with one it fences them', function (): void {
    $first = Business::factory()->create();
    $second = Business::factory()->create();
    Service::factory()->for($first)->create();
    Service::factory()->for($second)->create();

    $token = staffInApp(staffMember());
    asStaffToken($token)->assertOk();

    // After a staff request the database session carries no tenant, and the models see everything.
    expect(DB::selectOne("select current_setting('app.current_tenant', true) as tenant")->tenant)->toBe('')
        ->and(Service::query()->count())->toBe(2)
        ->and(DB::table('services')->count())->toBe(2);

    // The control: the same query under a business IS fenced, so the line above proves staff are not.
    DB::selectOne('select set_config(?, ?, false)', ['app.current_tenant', (string) $first->id]);

    expect(DB::table('services')->count())->toBe(1);

    DB::selectOne('select set_config(?, ?, false)', ['app.current_tenant', '']);
});
