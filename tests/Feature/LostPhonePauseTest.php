<?php

declare(strict_types=1);

use App\Actions\Admin\ReconcilePlatformEvents;
use App\Actions\Business\PauseForLostPhone;
use App\Actions\Business\ResumeAfterLostPhone;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventState;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Mail\AccountPasswordChanged;
use App\Mail\LostPhonePauseChanged;
use App\Models\Business;
use App\Models\LoginDevice;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Models\WhatsAppLinkEvent;
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

it('lists the recent team devices whose access was cut, marks the one that paused, and forgets them on resume', function (): void {
    $owner = lostPhoneOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id, 'name' => 'Lucía Agente']);
    $agent->syncRoles('agent');
    bridgeUnlinks();
    $desk = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';
    LoginDevice::factory()->for($owner)->create(['fingerprint' => LoginDevice::fingerprintFor($desk), 'user_agent' => $desk, 'last_login_at' => now()]);
    LoginDevice::factory()->for($owner)->create(['fingerprint' => LoginDevice::fingerprintFor(PHONE_AGENT), 'user_agent' => PHONE_AGENT, 'last_login_at' => now()->subHour()]);
    // A browser not seen for a year had no session to cut.
    LoginDevice::factory()->for($owner)->create(['fingerprint' => 'old', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Gecko/20100101 Firefox/120.0', 'last_login_at' => now()->subYear()]);
    appInstallToken($agent, 'agent-phone');

    app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD, LoginDevice::fingerprintFor($desk));

    $cut = collect($owner->business->fresh()->lost_phone_cut_sessions);
    expect($cut)->toHaveCount(3)
        ->and($cut->firstWhere('device', 'Chrome · Android'))->toMatchArray(['name' => $owner->name, 'app' => false, 'mobile' => true, 'current' => false])
        ->and($cut->firstWhere('device', 'Chrome · Windows'))->toMatchArray(['name' => $owner->name, 'current' => true])
        ->and($cut->firstWhere('name', 'Lucía Agente'))->toMatchArray(['app' => true, 'mobile' => true])
        ->and($cut->pluck('device'))->not->toContain('Firefox · Windows');

    $this->actingAs($owner)
        ->withSession(['lost_phone_pause_seen' => $owner->business->fresh()->lost_phone_paused_at->getTimestamp()])
        ->withHeader('User-Agent', $desk)
        ->get(route('whatsapp'))
        ->assertSee(__('whatsapp.lost_phone.cut_title'))
        ->assertSee('Chrome · Android')
        ->assertSee(__('whatsapp.lost_phone.cut_current'))
        ->assertSeeInOrder(['Lucía Agente', __('whatsapp.lost_phone.cut_app')]);

    app(ResumeAfterLostPhone::class)->handle($owner->business->fresh(), $owner);

    expect($owner->business->fresh()->lost_phone_cut_sessions)->toBeNull();
});

it('tells the admin board while a business is paused, and closes the line when it lifts', function (): void {
    $owner = lostPhoneOwner();
    bridgeUnlinks();

    app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD);

    $line = PlatformEvent::query()->where('kind', PlatformEventKind::LostPhonePaused->value)->sole();
    expect($line->about_business_id)->toBe($owner->business_id)
        ->and($line->state)->toBe(PlatformEventState::Open)
        ->and(PlatformEventKind::LostPhonePaused->target($owner->business_id))->toBe(['route' => 'admin.businesses', 'query' => ['negocio' => $owner->business_id]]);

    // One fact, one line: the unlink it caused raises no "rescan" line, now or on the hourly reconcile.
    WhatsAppLinkEvent::record($owner->business->fresh(), false, 'owner_unlinked');
    app(ReconcilePlatformEvents::class)->handle();
    expect(PlatformEvent::query()->active()->where('kind', PlatformEventKind::WhatsAppDisconnected->value)->count())->toBe(0);

    app(ResumeAfterLostPhone::class)->handle($owner->business->fresh(), $owner);

    expect($line->fresh()->state)->toBe(PlatformEventState::Resolved);
});

it('takes the login link straight to the pause card, and an agent home instead of a 403', function (): void {
    $this->get(route('lost-phone'))->assertRedirect(route('login'));
    $this->get(route('login'))->assertSee(__('auth.login.lost_phone_note'))->assertDontSee('data-testid="lost-phone-link"', false);

    $owner = lostPhoneOwner();
    $this->actingAs($owner)->get(route('lost-phone'))->assertRedirect(route('whatsapp').'#perdi-el-telefono');

    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles('agent');
    $this->actingAs($agent)->get(route('lost-phone'))->assertRedirect($agent->panelHome());
});

it('offers the lost-phone link on a plain login', function (): void {
    $this->get(route('login'))->assertSee('data-testid="lost-phone-link"', false)->assertSee(route('lost-phone'))->assertDontSee(__('auth.login.lost_phone_note'));
});

it('shows the pause on the admin directory row and on its ficha', function (): void {
    $owner = lostPhoneOwner();
    pausedBusinessOf($owner);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    livewire('admin.businesses.index')
        ->assertSeeHtml('data-testid="biz-paused-'.$owner->business_id.'"')
        ->assertSee(__('admin.businesses.lost_phone.tag'))
        ->call('toggle', $owner->business_id)
        ->assertSeeHtml('data-testid="biz-lost-phone-'.$owner->business_id.'"');
});

it('lets the admin pause for an owner who cannot sign in, killing her password and mailing her the way to a new one', function (): void {
    $owner = lostPhoneOwner();
    LoginDevice::factory()->for($owner)->create(['fingerprint' => LoginDevice::fingerprintFor(PHONE_AGENT), 'user_agent' => PHONE_AGENT, 'last_login_at' => now()]);
    bridgeUnlinks();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    livewire('admin.businesses.index')
        ->call('toggle', $owner->business_id)
        ->assertSeeHtml('data-testid="biz-pause-'.$owner->business_id.'"')
        ->call('pauseForLostPhone', $owner->business_id)
        ->assertDispatched('notify', fn (string $name, array $params): bool => $params['message'] === __('admin.businesses.lost_phone.done'));

    $business = $owner->business->fresh();
    expect($business->isPausedForLostPhone())->toBeTrue()
        ->and($business->lost_phone_paused_by)->toBeNull()
        ->and(Hash::check(OLD_PASSWORD, $owner->fresh()->password))->toBeFalse()
        ->and(PlatformEvent::query()->where('kind', PlatformEventKind::LostPhonePaused->value)->count())->toBe(1);
    Mail::assertQueued(LostPhonePauseChanged::class, fn (LostPhonePauseChanged $mail): bool => $mail->byStaff
        && $mail->hasTo($owner->email)
        && str_contains($mail->render(), route('password.request'))
        && str_contains($mail->render(), 'Chrome · Android'));
});

it('keeps support, who may only look at businesses, from pausing one', function (): void {
    $owner = lostPhoneOwner();
    $support = User::factory()->create(['email_verified_at' => now()]);
    $support->assignRole('support');
    $this->actingAs($support->refresh());

    livewire('admin.businesses.index')
        ->call('toggle', $owner->business_id)
        ->assertDontSeeHtml('data-testid="biz-pause-'.$owner->business_id.'"')
        ->call('pauseForLostPhone', $owner->business_id)
        ->assertForbidden();

    expect($owner->business->fresh()->isPausedForLostPhone())->toBeFalse();
});

it('mails the owner who paused the list of what the pause cut', function (): void {
    $owner = lostPhoneOwner();
    LoginDevice::factory()->for($owner)->create(['fingerprint' => LoginDevice::fingerprintFor(PHONE_AGENT), 'user_agent' => PHONE_AGENT, 'last_login_at' => now()]);
    bridgeUnlinks();

    app(PauseForLostPhone::class)->handle($owner->business, $owner, NEW_PASSWORD);

    Mail::assertQueued(LostPhonePauseChanged::class, fn (LostPhonePauseChanged $mail): bool => ! $mail->byStaff
        && str_contains($mail->render(), __('whatsapp.lost_phone.cut_title'))
        && str_contains($mail->render(), 'Chrome · Android'));
});

it('swaps the QR of the signup WhatsApp step for the paused banner', function (): void {
    $owner = lostPhoneOwner();
    pausedBusinessOf($owner);
    $this->actingAs($owner);

    livewire('business.step-whatsapp')
        ->assertSee(__('whatsapp.lost_phone.banner_title'))
        ->assertDontSeeHtml('wire:poll');
});

it('hands the phone app the paused card and the home banner in the web words', function (): void {
    $owner = lostPhoneOwner();
    $owner->business->subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->addDays(20)])->save();
    $token = appInstallToken($owner, 'owner-new-phone');
    $this->withHeaders(apiDeviceHeaders('owner-new-phone'));

    $this->withToken($token)->getJson('/api/v1/whatsapp')->assertOk()->assertJsonPath('data.paused', null);
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.profile.home.lost_phone', null);

    pausedBusinessOf($owner);
    // A fresh request would read the user afresh; the test reuses the guard's copy.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/whatsapp')
        ->assertOk()
        ->assertJsonPath('data.paused.title', __('whatsapp.lost_phone.paused_title'))
        ->assertJsonPath('data.paused.effects', __('whatsapp.lost_phone.effects'))
        ->assertJsonPath('data.paused.resume', __('whatsapp.lost_phone.app_resume'));
    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.home.lost_phone.title', __('whatsapp.lost_phone.banner_title'));
});
