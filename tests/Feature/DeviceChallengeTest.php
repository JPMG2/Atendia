<?php

declare(strict_types=1);

use App\Events\DeviceAdded;
use App\Mail\DeviceChallengeCode;
use App\Mail\NewDeviceLogin;
use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** Logs in from a never-seen browser and returns the mailed code. */
function startDeviceChallenge(User $user): string
{
    LoginDevice::factory()->for($user)->create();

    test()->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    $code = null;
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('an unknown device is challenged before any session opens', function (): void {
    Mail::fake();
    $user = User::factory()->create();
    LoginDevice::factory()->for($user)->create();

    $this->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('device.challenge'));

    // Right credentials, but no session and no device yet: the inbox decides.
    $this->assertGuest();
    expect($user->loginDevices()->count())->toBe(1);
    Mail::assertQueued(DeviceChallengeCode::class, fn (DeviceChallengeCode $mail): bool => $mail->hasTo($user->email));
    Mail::assertNotQueued(NewDeviceLogin::class);
});

test('a wrong code keeps the door shut', function (): void {
    Mail::fake();
    $user = User::factory()->create();
    $code = startDeviceChallenge($user);

    $this->post('/codigo-de-acceso', ['code' => $code === '111111' ? '222222' : '111111'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('an expired challenge bounces back to the login', function (): void {
    Mail::fake();
    $user = User::factory()->create();
    $code = startDeviceChallenge($user);

    $this->travel(11)->minutes();

    $this->post('/codigo-de-acceso', ['code' => $code])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the code screen without a pending challenge bounces to the login', function (): void {
    $this->get('/codigo-de-acceso')->assertRedirect(route('login'));
});

test('the code screen shows while the challenge is pending', function (): void {
    Mail::fake();
    startDeviceChallenge(User::factory()->create());

    $this->get('/codigo-de-acceso')
        ->assertOk()
        ->assertSee(__('security.challenge.title'));
});

test('resending issues a fresh code that opens the door', function (): void {
    Mail::fake();
    $user = User::factory()->create();
    startDeviceChallenge($user);

    $this->post('/codigo-de-acceso/reenviar')->assertRedirect();

    $codes = [];
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$codes): bool {
        $codes[] = $mail->code;

        return true;
    });

    expect($codes)->toHaveCount(2);

    $this->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/codigo-de-acceso', ['code' => end($codes)]);

    $this->assertAuthenticatedAs($user);
});

test('resending without a pending challenge bounces to the login', function (): void {
    $this->post('/codigo-de-acceso/reenviar')->assertRedirect(route('login'));
});

test('the code screen offers the resend behind its cooldown', function (): void {
    Mail::fake();
    startDeviceChallenge(User::factory()->create());

    $this->get('/codigo-de-acceso')
        ->assertSee(__('security.challenge.resend'))
        ->assertSee('wait: 30', false);
});

test('the sessions already open hear about the new device', function (): void {
    Event::fake([DeviceAdded::class]);
    Mail::fake();
    $user = User::factory()->create();
    $code = startDeviceChallenge($user);

    $this->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/codigo-de-acceso', ['code' => $code]);

    Event::assertDispatched(DeviceAdded::class);
});

test('the code screen masks the destination and submits itself', function (): void {
    Mail::fake();
    startDeviceChallenge(User::factory()->create(['email' => 'info@hidrofracweb.com']));

    $this->get('/codigo-de-acceso')
        ->assertSee(__('security.challenge.sent_to'))
        ->assertSee('i***@h***.com')
        ->assertSee('requestSubmit', false);
});

test('the masked email keeps only the first letters and the tld', function (): void {
    expect(User::factory()->make(['email' => 'info@hidrofracweb.com'])->maskedEmail())
        ->toBe('i***@h***.com');
});

test('the challenge mail carries the code', function (): void {
    $user = User::factory()->make(['name' => 'Prueba']);

    expect((new DeviceChallengeCode($user, '123456'))->render())
        ->toContain('123456')
        ->toContain(__('mail.challenge.body'));
});
