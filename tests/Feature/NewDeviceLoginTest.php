<?php

declare(strict_types=1);

use App\Events\DeviceAdded;
use App\Mail\DeviceChallengeCode;
use App\Mail\NewDeviceLogin;
use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

uses(RefreshDatabase::class);

test('the first device is remembered silently', function (): void {
    // The first login belongs to the registration itself: alerting there
    // would only scare a brand-new user.
    Mail::fake();
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($user->loginDevices()->count())->toBe(1);
    Mail::assertNothingOutgoing();
});

test('a login from an unseen device mails the security alert', function (): void {
    // The unknown device stops at the e-mail code gate first; only the right
    // code opens the session, records the device and fires the alert.
    Mail::fake();
    $user = User::factory()->create();
    LoginDevice::factory()->for($user)->create();

    $this->withHeader('User-Agent', 'Fresh Browser 1.0')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('device.challenge'));

    $code = null;
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $this->withHeader('User-Agent', 'Fresh Browser 1.0')
        ->post('/codigo-de-acceso', ['code' => $code]);

    $this->assertAuthenticatedAs($user);
    Mail::assertQueued(NewDeviceLogin::class, fn (NewDeviceLogin $mail): bool => $mail->hasTo($user->email));
    expect($user->loginDevices()->count())->toBe(2);
});

test('a known device never alerts again', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($user->loginDevices()->count())->toBe(1);
    Mail::assertNothingOutgoing();
});

test('the device records the approximate location of a public ip', function (): void {
    // Private IPs never geolocate (the guard shared with SetLocale), so the
    // test must arrive from a public address with the lookup faked.
    Mail::fake();
    Location::shouldReceive('get')->andReturn(tap(new Position, function (Position $position): void {
        $position->cityName = 'Buenos Aires';
        $position->countryName = 'Argentina';
    }));

    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '181.44.10.10'])
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($user->loginDevices()->sole()->location)->toBe('Buenos Aires, Argentina');
});

test('the alert mail shows the location when it is known', function (): void {
    $device = LoginDevice::factory()->make(['id' => 7, 'location' => 'Buenos Aires, Argentina']);
    $device->setRelation('user', User::factory()->make());

    expect((new NewDeviceLogin($device))->render())
        ->toContain(__('mail.new_device.location'))
        ->toContain('Buenos Aires, Argentina');
});

test('the alert mail carries the signed "this was not me" link', function (): void {
    $device = LoginDevice::factory()->make(['id' => 7]);
    $device->setRelation('user', User::factory()->make());

    expect((new NewDeviceLogin($device))->render())
        ->toContain(__('mail.new_device.not_me'))
        ->toContain('seguridad/dispositivos/7/cerrar')
        ->toContain('signature=');
});

test('the signed link kicks the device out and reassures', function (): void {
    $device = LoginDevice::factory()->create();
    $url = URL::temporarySignedRoute('security.devices.revoke', now()->addDays(7), ['device' => $device->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee(__('security.revoked.title'));

    expect(LoginDevice::query()->whereKey($device->id)->exists())->toBeFalse();
});

test('a link without a valid signature revokes nothing', function (): void {
    $device = LoginDevice::factory()->create();

    $this->get(route('security.devices.revoke', ['device' => $device->id]))->assertForbidden();

    expect(LoginDevice::query()->whereKey($device->id)->exists())->toBeTrue();
});

test('the device broadcast targets the owner and speaks the toast', function (): void {
    $device = LoginDevice::factory()->create([
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36',
    ]);
    $event = new DeviceAdded($device);

    expect((string) $event->broadcastOn()[0])->toBe('private-security.user.'.$device->user_id)
        ->and($event->broadcastAs())->toBe('device.added')
        ->and($event->broadcastWith()['message'])->toContain('Chrome · Windows')
        ->and($event->broadcastWith()['action']['label'])->toBe(__('profile.devices.review_action'))
        ->and($event->broadcastWith()['action']['url'])->toContain('#dispositivos');
});

test('the toast can carry a jump to the screen that solves it', function (): void {
    Livewire::test('toast')->assertSee('toast-action', false)->assertSee('toast.action.url', false);
});

test('the login remembers the last email used on the device', function (): void {
    // localStorage convenience: prefill on load, saved on submit; wrapped
    // in try/catch so private windows never break the form.
    $this->get('/login')
        ->assertSee('atendia-login-email', false)
        ->assertSee('recallEmail', false)
        ->assertSee('rememberEmail', false);
});
