<?php

declare(strict_types=1);

use App\Actions\Business\VerifyWhatsAppLink;
use App\Enums\WhatsAppLinkState;
use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppLinkEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');

    $this->seed(RolesAndPermissionsSeeder::class);
});

function bridgeState(string $state): void
{
    Http::fake(['http://evolution.test/*' => Http::response(['instance' => ['state' => $state]])]);
}

function linkedBusiness(array $attributes = []): Business
{
    return Business::factory()->create(['whatsapp_instance' => 'business-99', ...$attributes]);
}

test('a business that was never linked needs no probe to be called disconnected', function (): void {
    Http::fake();

    $state = app(VerifyWhatsAppLink::class)->handle(Business::factory()->create());

    expect($state)->toBe(WhatsAppLinkState::Disconnected);

    Http::assertNothingSent();
});

test('an open bridge stamps a column the webhook never got to', function (): void {
    bridgeState('open');

    $business = linkedBusiness();

    expect(app(VerifyWhatsAppLink::class)->handle($business))->toBe(WhatsAppLinkState::Connected)
        ->and($business->refresh()->whatsapp_connected_at)->not->toBeNull();
});

test('a lost pairing clears the stamp the panel was still showing', function (): void {
    bridgeState('close');

    $business = linkedBusiness(['whatsapp_connected_at' => now()->subDay()]);

    expect(app(VerifyWhatsAppLink::class)->handle($business))->toBe(WhatsAppLinkState::Disconnected)
        ->and($business->refresh()->whatsapp_connected_at)->toBeNull();
});

test('a socket stuck at connecting counts as down, not as almost there', function (): void {
    bridgeState('connecting');

    expect(app(VerifyWhatsAppLink::class)->handle(linkedBusiness()))->toBe(WhatsAppLinkState::Disconnected);
});

test('a silent bridge leaves the stamp alone instead of guessing', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    $business = linkedBusiness(['whatsapp_connected_at' => now()->subDay()]);

    expect(app(VerifyWhatsAppLink::class)->handle($business))->toBe(WhatsAppLinkState::Unverified)
        ->and($business->refresh()->whatsapp_connected_at)->not->toBeNull();
});

test('a stamp nobody has confirmed lately reads as unverified', function (): void {
    $business = linkedBusiness(['whatsapp_connected_at' => now()]);

    expect($business->linkState())->toBe(WhatsAppLinkState::Unverified);

    bridgeState('open');
    app(VerifyWhatsAppLink::class)->handle($business);

    expect($business->linkState())->toBe(WhatsAppLinkState::Connected);
});

test('the scheduled reconcile corrects every linked business', function (): void {
    bridgeState('close');

    $business = linkedBusiness(['whatsapp_connected_at' => now()->subDay()]);

    $this->artisan('atendia:whatsapp-reconcile')->assertSuccessful();

    expect($business->refresh()->whatsapp_connected_at)->toBeNull();
});

test('every transition leaves a row, so the week can be counted later', function (): void {
    // A sequence, not two fakes: a second Http::fake() of the same pattern
    // never wins over the first, so the line would read "open" twice.
    Http::fake(['http://evolution.test/*' => Http::sequence()
        ->push(['instance' => ['state' => 'open']])
        ->push(['instance' => ['state' => 'close']]),
    ]);

    $business = linkedBusiness();

    app(VerifyWhatsAppLink::class)->handle($business);
    app(VerifyWhatsAppLink::class)->handle($business);

    expect(WhatsAppLinkEvent::query()->where('business_id', $business->id)->pluck('connected')->all())
        ->toBe([true, false]);
});

test('a re-check of an unchanged state adds no row', function (): void {
    bridgeState('close');

    $business = linkedBusiness();

    app(VerifyWhatsAppLink::class)->handle($business);
    app(VerifyWhatsAppLink::class)->handle($business);

    expect(WhatsAppLinkEvent::query()->where('business_id', $business->id)->count())->toBe(0);
});

test('the history adds up the time a line spent down', function (): void {
    $business = linkedBusiness(['whatsapp_connected_at' => now(), 'created_at' => now()->subMonth()]);

    WhatsAppLinkEvent::query()->create([
        'business_id' => $business->id, 'connected' => false,
        'reason' => 'device_removed', 'occurred_at' => now()->subDays(2),
    ]);
    WhatsAppLinkEvent::query()->create([
        'business_id' => $business->id, 'connected' => true,
        'reason' => 'paired', 'occurred_at' => now()->subDays(2)->addHours(3),
    ]);

    $history = WhatsAppLinkEvent::reliability($business);

    expect($history['outages'])->toBe(1)
        ->and($history['downMinutes'])->toBe(180)
        ->and($history['lastReason'])->toBe('device_removed');
});

test('a fall nobody recovered from keeps counting up to now', function (): void {
    $business = linkedBusiness(['whatsapp_connected_at' => now(), 'created_at' => now()->subMonth()]);

    WhatsAppLinkEvent::query()->create([
        'business_id' => $business->id, 'connected' => false,
        'reason' => 'bridge_closed', 'occurred_at' => now()->subHours(5),
    ]);

    expect(WhatsAppLinkEvent::reliability($business)['downMinutes'])->toBe(300);
});

test('a young account is told which window the figure covers', function (): void {
    // Born yesterday: seven clean days is not a claim it can make, so the
    // card names the day it actually started counting from.
    $business = linkedBusiness(['whatsapp_connected_at' => now(), 'created_at' => now()->subDay()]);

    expect(WhatsAppLinkEvent::reliability($business)['since']->isSameDay(now()->subDay()))->toBeTrue();
});

test('a webhook naming a removed device records why it fell', function (): void {
    config()->set('services.evolution.webhook_secret', 'shared-secret');

    $business = linkedBusiness(['whatsapp_connected_at' => now()]);

    $this->postJson('/api/webhooks/evolution', [
        'event' => 'connection.update',
        'instance' => $business->whatsapp_instance,
        'data' => ['state' => 'close', 'statusReason' => 401],
    ], ['X-Webhook-Secret' => 'shared-secret'])->assertOk();

    expect(WhatsAppLinkEvent::query()->where('business_id', $business->id)->sole()->reason)
        ->toBe('device_removed');
});

test('the topbar pill says unverified rather than claiming a green link', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(linkedBusiness(['whatsapp_connected_at' => now()]))->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee(__('whatsapp.topbar_unverified'))
        ->assertDontSee(__('whatsapp.topbar_connected'));
});

test('the topbar pill greets the number that just paired, then shortens again', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([
        '0' => ['ownerJid' => '5492995243890@s.whatsapp.net', 'profileName' => 'Laboratorio Vida', 'profilePicUrl' => null],
    ])]);

    $business = linkedBusiness();
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    $pill = Livewire::actingAs($user)->test('whatsapp.pill')
        ->assertSee(__('whatsapp.topbar_disconnected'));

    $business->forceFill(['whatsapp_connected_at' => now()])->save();
    $business->markLinkVerified();

    $pill->dispatch('whatsapp:connected')
        ->assertSee(__('whatsapp.topbar_connected_as', ['name' => 'Laboratorio Vida']))
        ->call('greeted')
        ->assertSee(__('whatsapp.topbar_connected'))
        ->assertDontSee(__('whatsapp.topbar_disconnected'));
});

test('a silent bridge pairs without a greeting rather than with an empty one', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    $business = linkedBusiness(['whatsapp_connected_at' => now()]);
    $business->markLinkVerified();
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)->test('whatsapp.pill')
        ->dispatch('whatsapp:connected')
        ->assertSee(__('whatsapp.topbar_connected'));
});

test('the green pill carries the age of the bridge answer behind it', function (): void {
    $business = linkedBusiness(['whatsapp_connected_at' => now()]);
    $business->markLinkVerified();
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)->test('whatsapp.pill')
        ->assertSee('title="'.__('whatsapp.topbar_verified_ago', ['ago' => '']), false);
});

test('the pill rides the panel socket, so a fall reaches it without a reload', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(linkedBusiness())->save();

    $listeners = Livewire::actingAs($user)->test('whatsapp.pill')->instance()->getListeners();

    expect($listeners)->toHaveKey("echo-private:business.{$user->business_id},.panel.notified");
});
