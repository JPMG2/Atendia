<?php

declare(strict_types=1);

use App\Actions\Account\GenerateRecoveryCodes;
use App\Mail\AccountPasswordReset;
use App\Mail\DeviceChallengeCode;
use App\Mail\NewDeviceLogin;
use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** An account that already signed in from a browser, so the app is a stranger to it. */
function accountSeenOnTheWeb(array $attributes = [], array $business = []): User
{
    $user = payingUser($attributes, $business);
    LoginDevice::factory()->for($user)->create();

    return $user;
}

/** Asks for a token as the app; returns the response. */
function appLogin(User $user, string $deviceId = 'install-0000-aaaa-1111', string $password = 'password'): TestResponse
{
    return test()->withHeaders(apiDeviceHeaders($deviceId))->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'Pixel 8',
    ]);
}

/** The code the last challenge mailed. */
function appChallengeCode(): string
{
    $code = '';

    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

function answerChallenge(string $challengeId, string $code, string $deviceId = 'install-0000-aaaa-1111'): TestResponse
{
    return test()->withHeaders(apiDeviceHeaders($deviceId))->postJson('/api/v1/login/challenge', [
        'challenge_id' => $challengeId,
        'code' => $code,
    ]);
}

test('the first sign-in of an account with no devices remembers the install without an alert', function (): void {
    Mail::fake();
    $user = payingUser();

    appLogin($user)->assertOk();

    expect($user->loginDevices()->count())->toBe(1)->and($user->tokens()->count())->toBe(1);

    Mail::assertNotQueued(NewDeviceLogin::class);
});

test('sign-in refuses a call that does not name its install', function (): void {
    $user = payingUser();

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('device_id');

    expect($user->tokens()->count())->toBe(0);
});

test('an install the account already used gets its token and signing in again replaces it', function (): void {
    $user = payingUser();
    LoginDevice::factory()->for($user)->create(['fingerprint' => LoginDevice::fingerprintForApp('install-0000-aaaa-1111')]);

    appLogin($user)->assertOk()->assertJsonStructure(['data' => ['id'], 'token']);
    appLogin($user)->assertOk();

    expect($user->tokens()->count())->toBe(1);
});

test('an install the account never used gets a code, not a token', function (): void {
    Mail::fake();
    $user = accountSeenOnTheWeb();

    appLogin($user)->assertStatus(202)
        ->assertJsonStructure(['challenge' => ['id', 'via_whatsapp', 'destination', 'expires_in']])
        ->assertJsonPath('challenge.via_whatsapp', false)
        ->assertJsonMissingPath('token');

    expect($user->tokens()->count())->toBe(0);
    Mail::assertQueued(DeviceChallengeCode::class, fn ($mail): bool => $mail->hasTo($user->email));
});

test('the right code opens the door, remembers the install and warns by mail', function (): void {
    Mail::fake();
    $user = accountSeenOnTheWeb();
    $id = appLogin($user)->json('challenge.id');

    answerChallenge($id, appChallengeCode())->assertOk()->assertJsonStructure(['data' => ['id'], 'token']);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->loginDevices()->where('fingerprint', LoginDevice::fingerprintForApp('install-0000-aaaa-1111'))->exists())->toBeTrue();
    Mail::assertQueued(NewDeviceLogin::class, fn ($mail): bool => $mail->hasTo($user->email));
});

test('a wrong code is refused and five of them kill the challenge', function (): void {
    Mail::fake();
    $user = accountSeenOnTheWeb();
    $id = appLogin($user)->json('challenge.id');
    $right = appChallengeCode();
    $wrong = $right === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $try) {
        answerChallenge($id, $wrong)->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    answerChallenge($id, $right)->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

test('a challenge only answers to the install that asked for it', function (): void {
    Mail::fake();
    $user = accountSeenOnTheWeb();
    $id = appLogin($user)->json('challenge.id');

    answerChallenge($id, appChallengeCode(), 'another-install-9999')->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

test('a resend replaces the code and stops after three', function (): void {
    Mail::fake();
    $user = accountSeenOnTheWeb();
    $id = appLogin($user)->json('challenge.id');
    $first = appChallengeCode();

    foreach (range(1, 3) as $resend) {
        $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge/resend', ['challenge_id' => $id])->assertOk();
    }

    $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge/resend', ['challenge_id' => $id])
        ->assertUnprocessable()->assertJsonValidationErrors('challenge_id');

    Mail::assertQueued(DeviceChallengeCode::class, 4);

    $latest = null;
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$latest): bool {
        $latest = $mail->code;

        return true;
    });

    if ($first !== $latest) {
        answerChallenge($id, $first)->assertUnprocessable();
    }

    answerChallenge($id, $latest)->assertOk();
});

test('a backup code opens the door once when the phone is lost', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
    $user = accountSeenOnTheWeb(business: ['fallback_whatsapp_number' => '+54 9 11 2233-4455']);
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    $codes = app(GenerateRecoveryCodes::class)->handle($user);

    $id = appLogin($user)->assertStatus(202)->json('challenge.id');
    $headers = apiDeviceHeaders();

    $this->withHeaders($headers)->postJson('/api/v1/login/challenge/recovery', ['challenge_id' => $id, 'recovery_code' => 'wrong-codex'])
        ->assertUnprocessable()->assertJsonValidationErrors('recovery_code');

    $this->withHeaders($headers)->postJson('/api/v1/login/challenge/recovery', ['challenge_id' => $id, 'recovery_code' => strtoupper($codes[0])])
        ->assertOk()->assertJsonStructure(['token']);

    expect($user->refresh()->recoveryCodesLeft())->toBe(9);
});

test('five wrong passwords lock the sign-in even for the right one, like on the web', function (): void {
    $user = payingUser();

    foreach (range(1, 5) as $try) {
        appLogin($user, password: 'not-the-password')->assertUnprocessable();
    }

    appLogin($user)->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonMissingPath('token');

    expect($user->tokens()->count())->toBe(0);
});

test('a closed account inside its restore window comes back with the right password', function (): void {
    $user = payingUser();
    $user->delete();

    appLogin($user)->assertOk()->assertJsonStructure(['token']);

    expect($user->refresh()->trashed())->toBeFalse();
});

test('a closed account past its restore window cannot come back by itself', function (): void {
    $user = payingUser();
    $user->delete();
    $user->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    appLogin($user)->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

test('every call with a token fixes the business the database row security reads', function (): void {
    $user = payingUser();
    DB::select("select set_config('app.current_tenant', '999999', false)");
    $token = appInstallToken($user);

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertOk();

    expect(DB::selectOne("select current_setting('app.current_tenant', true) as tenant")->tenant)->toBe((string) $user->business_id);
});

test('a call with a token but without the install id is refused', function (): void {
    Sanctum::actingAs(payingUser());

    $this->getJson('/api/v1/me')->assertStatus(400)->assertJsonPath('code', 'device_required');
});

test('a token dies the moment its device is no longer listed', function (): void {
    $user = accountSeenOnTheWeb();
    $token = $user->createToken(LoginDevice::fingerprintForApp('install-0000-aaaa-1111'))->plainTextToken;

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')
        ->assertUnauthorized()->assertJsonPath('code', 'device_revoked');

    expect($user->tokens()->count())->toBe(0);
});

test('a listed device keeps its access', function (): void {
    $token = appInstallToken(payingUser());

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertOk();
});

test('a token shown with another install id is refused and stays alive for its own install', function (): void {
    $user = payingUser();
    $token = appInstallToken($user, 'install-0000-aaaa-1111');
    LoginDevice::factory()->for($user)->create(['fingerprint' => LoginDevice::fingerprintForApp('install-9999-bbbb-2222')]);

    $this->withToken($token)->withHeaders(apiDeviceHeaders('install-9999-bbbb-2222'))->getJson('/api/v1/me')
        ->assertUnauthorized()->assertJsonPath('code', 'device_revoked');

    expect($user->tokens()->count())->toBe(1);
    $this->withToken($token)->withHeaders(apiDeviceHeaders('install-0000-aaaa-1111'))->getJson('/api/v1/me')->assertOk();
});

test('an account with no device rows left is no free pass for a token', function (): void {
    $user = payingUser();
    $token = $user->createToken(LoginDevice::fingerprintForApp('install-0000-aaaa-1111'))->plainTextToken;

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')
        ->assertUnauthorized()->assertJsonPath('code', 'device_revoked');

    expect($user->tokens()->count())->toBe(0);
});

test('closing the account ends its tokens', function (): void {
    $user = payingUser();
    $token = appInstallToken($user);

    $user->delete();

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertUnauthorized();
});

test('resetting the password through the web ends the account tokens', function (): void {
    Mail::fake();
    $user = payingUser();
    $token = appInstallToken($user);

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(AccountPasswordReset::class, function (AccountPasswordReset $mail) use ($user): bool {
        $this->post('/reset-password', [
            'token' => $mail->token,
            'email' => $user->email,
            'password' => 'Segura#2026',
            'password_confirmation' => 'Segura#2026',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($user->tokens()->count())->toBe(0);
    $this->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertUnauthorized();
});

test('the code gate limits each challenge, not every person behind one address', function (): void {
    $stranger = str_repeat('a', 48);
    $other = str_repeat('b', 48);
    $call = fn (string $id) => $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => '123456']);

    foreach (range(1, 10) as $try) {
        $call($stranger)->assertUnprocessable();
    }

    $call($stranger)->assertStatus(429);
    $call($other)->assertUnprocessable();
});

test('the language is the one the phone announces, inside the supported list', function (string $header, string $expected): void {
    $token = appInstallToken(payingUser());
    // Start somewhere else, so a fallback to the default proves the middleware ran.
    app()->setLocale($expected === 'es_AR' ? 'es_VE' : 'es_AR');

    $this->withToken($token)->withHeaders([...apiDeviceHeaders(), 'Accept-Language' => $header])->getJson('/api/v1/me')->assertOk();

    expect(app()->getLocale())->toBe($expected);
})->with([
    'argentine spanish' => ['es-AR,es;q=0.9,en;q=0.5', 'es_AR'],
    'venezuelan spanish' => ['es-VE', 'es_VE'],
    'another spanish falls to the neutral one' => ['es-MX', 'es'],
    'an unsupported language falls to the default' => ['fr-FR,fr;q=0.9', 'es'],
]);
