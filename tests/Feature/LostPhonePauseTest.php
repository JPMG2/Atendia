<?php

declare(strict_types=1);

use App\Actions\Business\PauseForLostPhone;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Mail\AccountPasswordChanged;
use App\Mail\LostPhonePauseChanged;
use App\Models\Business;
use App\Models\LoginDevice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

const OLD_PASSWORD = 'Clave-Vieja-2026!';
const NEW_PASSWORD = 'Clave-Nueva-2026!';
const PHONE_AGENT = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36';

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.webhook_secret', 'shared-secret');
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

/** An owner whose number is linked, with an alert phone and WhatsApp login codes on. */
function lostPhoneOwner(): User
{
    $business = Business::factory()->create([
        'whatsapp_instance' => 'negocio-lost',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '5491155550101',
    ]);
    $owner = User::factory()->create([
        'business_id' => $business->id,
        'password' => Hash::make(OLD_PASSWORD),
        'two_factor_whatsapp_at' => now(),
        'whatsapp' => '5491155550101',
    ]);
    $owner->syncRoles('client');

    return $owner->refresh();
}

/** The bridge logs the number out and then reports it closed. */
function bridgeUnlinks(): void
{
    $state = 'open';
    Http::fake(function (HttpRequest $request) use (&$state) {
        if (str_contains($request->url(), '/instance/logout/')) {
            $state = 'close';

            return Http::response(['status' => 'SUCCESS']);
        }

        return Http::response(['instance' => ['state' => $state]]);
    });
}

function pausedBusinessOf(User $owner): Business
{
    $owner->business->forceFill(['lost_phone_paused_at' => now(), 'lost_phone_paused_by' => $owner->id])->save();

    return $owner->business->fresh();
}

it('stops everything that could speak through the phone, and leaves billing alone', function (): void {
    $owner = lostPhoneOwner();
    bridgeUnlinks();
    $subscription = $owner->business->subscription->only(['status', 'current_period_ends_at', 'trial_ends_at']);

    app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD);

    $business = $owner->business->fresh();
    $owner = $owner->fresh();

    expect($business->isPausedForLostPhone())->toBeTrue()
        ->and($business->lost_phone_paused_by)->toBe($owner->id)
        ->and($business->canMessageCustomers())->toBeFalse()
        ->and($business->isSilenced())->toBeTrue()
        // Alerts and login codes no longer reach the stolen phone: codes fall back to e-mail.
        ->and($business->ownerWhatsAppDigits())->toBe('')
        ->and($owner->sendsLoginCodesByWhatsApp())->toBeFalse()
        ->and($business->whatsapp_connected_at)->toBeNull()
        // The password saved on the stolen phone stops working.
        ->and(Hash::check(OLD_PASSWORD, $owner->password))->toBeFalse()
        ->and(Hash::check(NEW_PASSWORD, $owner->password))->toBeTrue()
        ->and($business->subscription->only(['status', 'current_period_ends_at', 'trial_ends_at']))->toEqual($subscription);

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/instance/logout/negocio-lost'));
    Mail::assertQueued(LostPhonePauseChanged::class, fn (LostPhonePauseChanged $mail): bool => $mail->paused && $mail->hasTo($owner->email));
    Mail::assertQueued(AccountPasswordChanged::class);
});

it('closes every app install and remember-me cookie of the whole team', function (): void {
    $owner = lostPhoneOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles('agent');
    bridgeUnlinks();
    appInstallToken($owner, 'owner-phone');
    appInstallToken($agent, 'agent-phone');
    $remember = $agent->remember_token;

    app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD);

    expect($owner->tokens()->count())->toBe(0)
        ->and($agent->tokens()->count())->toBe(0)
        ->and(LoginDevice::query()->whereIn('fingerprint', [LoginDevice::fingerprintForApp('owner-phone'), LoginDevice::fingerprintForApp('agent-phone')])->count())->toBe(0)
        ->and($agent->fresh()->remember_token)->not->toBe($remember);
});

it('ends a web session opened before the pause even on a phone with the same browser', function (): void {
    $owner = lostPhoneOwner();
    LoginDevice::factory()->for($owner)->create(['fingerprint' => LoginDevice::fingerprintFor(PHONE_AGENT), 'user_agent' => PHONE_AGENT]);
    pausedBusinessOf($owner);

    // The stolen phone's session: same user agent as the one that paused, but it never saw the pause.
    $this->actingAs($owner)
        ->withHeader('User-Agent', PHONE_AGENT)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps the session that paused, and lets the old password nowhere near the pause', function (): void {
    $owner = lostPhoneOwner();
    bridgeUnlinks();
    $this->actingAs($owner);

    livewire('whatsapp.lost-phone')
        ->set('pauseForm.current_password', OLD_PASSWORD)
        ->set('pauseForm.password', NEW_PASSWORD)
        ->set('pauseForm.password_confirmation', NEW_PASSWORD)
        ->call('pause')
        ->assertDispatched('notify', fn (string $name, array $params): bool => $params['message'] === __('whatsapp.lost_phone.paused'));

    // Admitting the session that paused needs a real request session: LostPhoneBrowserTest walks it.
    $pausedAt = $owner->business->fresh()->lost_phone_paused_at->getTimestamp();

    $this->withSession(['lost_phone_pause_seen' => $pausedAt])
        ->get(route('whatsapp'))
        ->assertOk()
        ->assertSee(__('whatsapp.lost_phone.paused_title'))
        ->assertSee(__('whatsapp.lost_phone.banner_title'))
        ->assertDontSee(__('whatsapp.connect.cta'));

    // Whoever kept the stolen phone's saved password cannot lift it.
    livewire('whatsapp.lost-phone')
        ->set('resumeForm.current_password', OLD_PASSWORD)
        ->call('resume')
        ->assertHasErrors('current_password');

    expect($owner->business->fresh()->isPausedForLostPhone())->toBeTrue();
});

it('will not pause without the current password', function (): void {
    $owner = lostPhoneOwner();
    $this->actingAs($owner);

    livewire('whatsapp.lost-phone')
        ->set('pauseForm.current_password', 'no-es-esta')
        ->set('pauseForm.password', NEW_PASSWORD)
        ->set('pauseForm.password_confirmation', NEW_PASSWORD)
        ->call('pause')
        ->assertHasErrors('current_password');

    expect($owner->business->fresh()->isPausedForLostPhone())->toBeFalse();
});

it('unlinks the number even when the stamp already said it was down', function (): void {
    $owner = lostPhoneOwner();
    $owner->business->update(['whatsapp_connected_at' => null]);
    bridgeUnlinks();

    expect(app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD))->toBeTrue();

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/instance/logout/negocio-lost'));
});

it('holds the pause even when the bridge cannot unlink the number', function (): void {
    $owner = lostPhoneOwner();
    Http::fake(['*' => Http::response(['instance' => ['state' => 'open']])]);

    $unlinked = app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD);

    $business = $owner->business->fresh();

    expect($unlinked)->toBeFalse()
        ->and($business->isPausedForLostPhone())->toBeTrue()
        ->and($business->canMessageCustomers())->toBeFalse();
});

it('unlinks again instead of stamping a number that pairs during the pause', function (): void {
    $owner = lostPhoneOwner();
    $owner->business->update(['whatsapp_connected_at' => null]);
    pausedBusinessOf($owner);
    Http::fake(['*' => Http::response(['instance' => ['state' => 'open']])]);

    $this->postJson('/api/webhooks/evolution', [
        'event' => 'connection.update',
        'instance' => 'negocio-lost',
        'data' => ['state' => 'open'],
    ], ['X-Webhook-Secret' => 'shared-secret'])->assertOk();

    expect($owner->business->fresh()->whatsapp_connected_at)->toBeNull();
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/instance/logout/negocio-lost'));

    // A QR tab left open stops renewing the code, and the card does not claim the link.
    $this->actingAs($owner);
    livewire('whatsapp.link')->set('linking', true)->call('checkLink')->assertSet('linking', false)->assertSet('qr', null);
    expect($owner->business->fresh()->whatsapp_connected_at)->toBeNull();
});

it('gives a team phone nothing back while paused, not even the list of waiting customers', function (): void {
    $owner = lostPhoneOwner();
    pausedBusinessOf($owner);
    Http::fake(['*' => Http::response(['status' => 'PENDING'])]);

    (new ProcessIncomingWhatsAppMessage('negocio-lost', '5491155550101', 'Dueña', 'hola', 'MSG-LOST'))->handle();

    Http::assertNothingSent();
});

it('asks the new password to lift the pause, and mails the receipt', function (): void {
    $owner = lostPhoneOwner();
    $owner->forceFill(['password' => Hash::make(NEW_PASSWORD)])->save();
    pausedBusinessOf($owner);
    $this->actingAs($owner)->withSession(['lost_phone_pause_seen' => $owner->business->fresh()->lost_phone_paused_at->getTimestamp()]);

    livewire('whatsapp.lost-phone')
        ->set('resumeForm.current_password', NEW_PASSWORD)
        ->call('resume')
        ->assertDispatched('notify', fn (string $name, array $params): bool => $params['message'] === __('whatsapp.lost_phone.resumed'));

    expect($owner->business->fresh()->isPausedForLostPhone())->toBeFalse()
        ->and($owner->business->fresh()->ownerWhatsAppDigits())->toBe('5491155550101');
    Mail::assertQueued(LostPhonePauseChanged::class, fn (LostPhonePauseChanged $mail): bool => ! $mail->paused);
});

it('keeps a teammate without business rights out of the pause', function (): void {
    $owner = lostPhoneOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id, 'password' => Hash::make(OLD_PASSWORD)]);
    $agent->syncRoles('agent');
    $this->actingAs($agent);

    $this->get(route('whatsapp'))->assertForbidden();

    livewire('whatsapp.lost-phone')
        ->set('pauseForm.current_password', OLD_PASSWORD)
        ->set('pauseForm.password', NEW_PASSWORD)
        ->set('pauseForm.password_confirmation', NEW_PASSWORD)
        ->call('pause');

    expect($owner->business->fresh()->isPausedForLostPhone())->toBeFalse();
});

it('keeps the paused banner on every client screen of a session that saw the pause', function (): void {
    $owner = lostPhoneOwner();
    $business = pausedBusinessOf($owner);
    $business->subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->addDays(20)])->save();

    $this->actingAs($owner)
        ->withSession(['lost_phone_pause_seen' => $business->lost_phone_paused_at->getTimestamp()])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('whatsapp.lost_phone.banner_title'))
        ->assertSee(route('whatsapp'));
});
