<?php

declare(strict_types=1);

use App\Actions\Account\GenerateRecoveryCodes;
use App\Actions\Account\UseRecoveryCode;
use App\Mail\AccountClosed;
use App\Mail\AccountEmailChangeNotice;
use App\Mail\AccountEmailChangeRequested;
use App\Mail\AccountEmailUpdated;
use App\Mail\AccountEmailVerification;
use App\Mail\AccountPasswordChanged;
use App\Mail\AccountPasswordReset;
use App\Mail\AccountTwoFactorChanged;
use App\Mail\DeviceChallengeCode;
use App\Models\Business;
use App\Models\LoginDevice;
use App\Models\User;
use App\Services\SignedLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Settings extras: photo, password age, email verification, WhatsApp 2FA
|--------------------------------------------------------------------------
| The photo is cropped to a 256px square and becomes the topbar's only
| identity; the checkup tells how old the password is; an unverified
| address gets its link on demand; and two-step verification sends the
| login codes to the owner's WhatsApp once the number is proven.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    Storage::fake('public');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.instance', 'atendia-demo');
});

function ownerWithPhone(): User
{
    $business = Business::factory()->create(['fallback_whatsapp_number' => '+54 9 11 2233-4455']);

    return User::factory()->create(['business_id' => $business->id])->refresh();
}

/** The code inside the WhatsApp text the fake Evolution received. */
function lastWhatsAppCode(): string
{
    $code = '';

    Http::assertSent(function (Request $request) use (&$code): bool {
        preg_match('/\d{6}/', (string) $request['text'], $match);
        $code = $match[0] ?? $code;

        return true;
    });

    return $code;
}

test('a profile photo is cropped to a 256px square and replaces the old one', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user->refresh());

    Livewire::test('settings.section-profile')
        ->set('form.avatar_file', UploadedFile::fake()->image('me.jpg', 900, 600))
        ->call('save')
        ->assertHasNoErrors();

    $first = $user->refresh()->avatar_path;
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($first));

    expect($first)->toEndWith('.webp')->and([$width, $height])->toBe([256, 256]);

    Livewire::test('settings.section-profile')
        ->set('form.avatar_file', UploadedFile::fake()->image('new.png', 300, 300))
        ->call('save');

    Storage::disk('public')->assertMissing($first);
});

test('removing the photo brings the initials back', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user->refresh());

    Livewire::test('settings.section-profile')
        ->set('form.avatar_file', UploadedFile::fake()->image('me.jpg', 300, 300))
        ->call('save')
        ->call('removeAvatar');

    expect($user->refresh()->avatar_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('avatars'))->toBe([]);
});

test('an svg or oversized file is refused as a photo', function (UploadedFile $file): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('settings.section-profile')
        ->set('form.avatar_file', $file)
        ->call('save')
        ->assertHasErrors('avatar_file');
})->with([
    'svg' => fn (): UploadedFile => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'too big' => fn (): UploadedFile => UploadedFile::fake()->image('huge.jpg')->size(6000),
]);

test('the topbar shows only the face: the photo when there is one', function (): void {
    $user = User::factory()->create(['name' => 'Ana Pérez']);
    $user->forceFill(['avatar_path' => 'avatars/ana.webp'])->save();

    $html = $this->actingAs($user->refresh())->get(route('dashboard'))->assertSuccessful()->getContent();

    expect($html)->toContain(Storage::disk('public')->url('avatars/ana.webp'))
        ->not->toContain('topbar-user-meta')
        ->toContain('aria-label="Ana Pérez"');
});

test('a password change starts the checkup clock', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user->refresh());

    $this->get(route('settings'))->assertSee('Sin cambios desde que creaste la cuenta');

    Livewire::test('settings.section-password')
        ->set('form.current_password', 'password')
        ->set('form.password', 'Nueva#Clave2026')
        ->set('form.password_confirmation', 'Nueva#Clave2026')
        ->call('save');

    expect($user->refresh()->password_changed_at)->not->toBeNull();
    $this->get(route('settings'))->assertSee('La cambiaste');
});

test('an unverified address gets its link on demand, throttled', function (): void {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user->refresh());

    $this->get(route('settings'))->assertSee(__('settings.email.verify'))->assertSee(__('profile.checkup.email_verify_link'));

    $component = Livewire::test('settings.section-email');

    foreach (range(1, 4) as $ignored) {
        $component->call('sendVerification');
    }

    Mail::assertQueued(AccountEmailVerification::class, 3);
});

test('verify-it-now in the checkup sends the link right there, no redirect', function (): void {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user->refresh());

    Livewire::test('settings.send-verification')
        ->call('send')
        ->assertNoRedirect()
        ->assertDispatched('notify', type: 'success', message: __('settings.email.verify_sent', ['email' => $user->email]));

    Mail::assertQueued(AccountEmailVerification::class, fn ($mail): bool => $mail->hasTo($user->email));
});

test('the verification link works from any device, even signed in as someone else', function (): void {
    $user = User::factory()->unverified()->create();
    $stranger = User::factory()->create();

    $url = SignedLink::temporary('settings.email.verify', now()->addHour(), [
        'user' => $user->id,
        'hash' => sha1($user->email),
    ]);

    // The 2026-09-23 report: opened on another computer, another session → 403.
    $this->actingAs($stranger->refresh())->get($url)
        ->assertSuccessful()
        ->assertSee(__('settings.links.verified_title'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and($stranger->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('a verification link for an older address or without signature changes nothing', function (): void {
    $user = User::factory()->unverified()->create();

    $stale = SignedLink::temporary('settings.email.verify', now()->addHour(), [
        'user' => $user->id,
        'hash' => sha1('old@shop.test'),
    ]);

    $this->get($stale)->assertSee(__('settings.links.invalid_title'));
    $this->get(route('settings.email.verify', ['user' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

test('two-step verification needs a phone to send to', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('settings.section-two-factor')
        ->assertSee(__('settings.two_factor.no_phone'))
        ->assertSee(__('settings.two_factor.go_contact'));
});

test('two-step verification turns on only after the number proves itself', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
    $user = ownerWithPhone();
    $this->actingAs($user);

    $component = Livewire::test('settings.section-two-factor')
        ->set('form.current_password', 'wrong')
        ->call('sendCode')
        ->assertHasErrors('current_password');

    Http::assertNothingSent();

    $component->set('form.current_password', 'password')->call('sendCode')->assertHasNoErrors()->assertSet('codeSent', true);

    Http::assertSent(fn (Request $request): bool => $request['number'] === '5491122334455');
    $code = lastWhatsAppCode();

    $component->set('form.code', $code === '111111' ? '222222' : '111111')->call('activate')->assertHasErrors('code');
    expect($user->refresh()->two_factor_whatsapp_at)->toBeNull();

    $component->set('form.code', $code)->call('activate')->assertHasNoErrors()
        ->assertSee(__('settings.two_factor.active'))
        ->assertSee(__('settings.two_factor.codes_warning'));

    expect($user->refresh()->sendsLoginCodesByWhatsApp())->toBeTrue()
        ->and($user->recoveryCodesLeft())->toBe(10)
        ->and($component->get('form.recovery_codes'))->toHaveCount(10);

    Mail::assertQueued(AccountTwoFactorChanged::class, fn ($mail): bool => $mail->enabled && $mail->hasTo($user->email));
});

test('with two-step on, a new device gets its code by whatsapp instead of mail', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
    $user = ownerWithPhone();
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    LoginDevice::factory()->for($user)->create();

    $this->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('device.challenge'));

    Mail::assertNotQueued(DeviceChallengeCode::class);
    $code = lastWhatsAppCode();

    $this->get(route('device.challenge'))->assertSee(__('security.challenge_whatsapp.title'))->assertSee('•••• 4455');

    $this->post('/codigo-de-acceso', ['code' => $code]);
    $this->assertAuthenticatedAs($user);
});

test('turning two-step off re-asks the password', function (): void {
    $user = ownerWithPhone();
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    $this->actingAs($user);

    Livewire::test('settings.section-two-factor')
        ->set('form.current_password', 'wrong')
        ->call('disable')
        ->assertHasErrors('current_password')
        ->set('form.current_password', 'password')
        ->call('disable')
        ->assertHasNoErrors();

    expect($user->refresh()->two_factor_whatsapp_at)->toBeNull()
        ->and($user->recoveryCodesLeft())->toBe(0);

    Mail::assertQueued(AccountTwoFactorChanged::class, fn ($mail): bool => ! $mail->enabled);
});

test('a whatsapp outage sends the login code by mail and says so', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);
    $user = ownerWithPhone();
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    LoginDevice::factory()->for($user)->create();

    $this->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    Mail::assertQueued(DeviceChallengeCode::class, fn ($mail): bool => $mail->hasTo($user->email));

    $this->get(route('device.challenge'))
        ->assertSee(__('security.challenge.whatsapp_failed'))
        ->assertSee(__('security.challenge.title'));
});

test('a whatsapp outage during setup is said, not faked as sent', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);
    $this->actingAs(ownerWithPhone());

    Livewire::test('settings.section-two-factor')
        ->set('form.current_password', 'password')
        ->call('sendCode')
        ->assertHasErrors('current_password')
        ->assertSet('codeSent', false);
});

test('a backup code opens the door once when the phone is lost', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
    $user = ownerWithPhone();
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    $codes = app(GenerateRecoveryCodes::class)->handle($user);
    LoginDevice::factory()->for($user)->create();

    $this->withHeader('User-Agent', 'Unknown Browser 9.0')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->get(route('device.challenge'))->assertSee(__('security.challenge.use_recovery'));

    $this->post(route('device.challenge.recovery'), ['recovery_code' => 'wrong-codex'])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();

    $this->post(route('device.challenge.recovery'), ['recovery_code' => strtoupper($codes[0])]);

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->recoveryCodesLeft())->toBe(9);
});

test('a new set of backup codes kills the old one', function (): void {
    $user = ownerWithPhone();
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    $old = app(GenerateRecoveryCodes::class)->handle($user);
    $this->actingAs($user->refresh());

    Livewire::test('settings.section-two-factor')
        ->assertSee('Te quedan 10 códigos de respaldo')
        ->set('form.current_password', 'password')
        ->call('regenerateCodes')
        ->assertHasNoErrors()
        ->assertSee(__('settings.two_factor.codes_warning'));

    expect(app(UseRecoveryCode::class)->handle($user->refresh(), $old[0]))->toBeFalse();
});

test('the two-step receipt mails render', function (bool $enabled, string $copy): void {
    $user = User::factory()->create(['name' => 'Ana']);

    expect((new AccountTwoFactorChanged($user, $enabled))->render())->toContain(__($copy.'.title'));
})->with([
    'on' => [true, 'mail.account.two_factor_on'],
    'off' => [false, 'mail.account.two_factor_off'],
]);

test('no account mail ever renders a raw translation key', function (string $mailable, array $arguments): void {
    $user = User::factory()->create(['name' => 'Ana']);
    $user->forceFill(['pending_email' => 'new@shop.test'])->save();

    $html = (new $mailable($user->refresh(), ...$arguments))->render();

    expect($html)->not->toContain('mail.');
})->with([
    'verify' => [AccountEmailVerification::class, []],
    'two-step on' => [AccountTwoFactorChanged::class, [true]],
    'two-step off' => [AccountTwoFactorChanged::class, [false]],
    'password changed' => [AccountPasswordChanged::class, []],
    'email change' => [AccountEmailChangeRequested::class, []],
    'email notice' => [AccountEmailChangeNotice::class, ['new@shop.test']],
    'email updated' => [AccountEmailUpdated::class, []],
    'closed' => [AccountClosed::class, []],
    'password reset' => [AccountPasswordReset::class, ['token']],
]);

test('a mailed link survives the http-to-https swap of the proxy', function (): void {
    // The 2026-09-23 report: the worker signs from APP_URL (http), the reader
    // opens it over https — an absolute signature answered 403.
    $user = User::factory()->unverified()->create();
    $link = SignedLink::temporary('settings.email.verify', now()->addHour(), ['user' => $user->id, 'hash' => sha1($user->email)]);

    $this->get(preg_replace('/^http:/', 'https:', $link))->assertSuccessful()->assertSee(__('settings.links.verified_title'));
});

test('signing up mails the verification link through the channel', function (): void {
    $this->post('/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@shop.test',
        'password' => 'Segura#2026',
        'password_confirmation' => 'Segura#2026',
    ]);

    Mail::assertQueued(AccountEmailVerification::class, fn ($mail): bool => $mail->hasTo('ana@shop.test'));
});

test('signing up from the mobile app mails it too', function (): void {
    $this->postJson('/api/v1/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@shop.test',
        'password' => 'Segura#2026',
        'password_confirmation' => 'Segura#2026',
        'device_name' => 'iPhone',
    ])->assertCreated();

    Mail::assertQueued(AccountEmailVerification::class, fn ($mail): bool => $mail->hasTo('ana@shop.test'));
});
