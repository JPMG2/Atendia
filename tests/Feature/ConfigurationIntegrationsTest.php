<?php

declare(strict_types=1);

use App\Enums\IntegrationState;
use App\Models\Menu;
use App\Models\User;
use App\Services\Integrations\IntegrationHealth;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Integrations screen (admin panel)
|--------------------------------------------------------------------------
| The health board of everything AtendIa consumes: on, answering, or where
| to look when something fails. Probes run after the first paint, bounded by
| timeouts, and one integration can be re-probed alone after a fix.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** An admin, which is what the whole screen is gated behind. */
function integrationsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

/** Answers every HTTP probe happily and every TCP probe with an open port. */
function fakeHealthyWorld(): void
{
    Http::fake([
        '*evolution-api*' => Http::response(['status' => 200, 'version' => '2.1.1']),
        '*n8n*' => Http::response(['status' => 'ok']),
        '*chatwoot*' => Http::response(['version' => '3.12.0']),
        '*openai.com*' => Http::response(['data' => []]),
        '*' => Http::response([], 200),
    ]);

    app()->bind(IntegrationHealth::class, fn (): IntegrationHealth => new IntegrationHealth(
        tcpProbe: fn (): bool => true,
    ));
}

test('a guest is redirected to login from the integrations page', function (): void {
    $this->get('/admin/integrations')->assertRedirect(route('login'));
});

test('a client cannot reach the integrations page', function (): void {
    $client = User::factory()->create(); // the factory assigns the client role

    $this->actingAs($client)->get('/admin/integrations')->assertForbidden();
});

test('an admin sees every integration, still probing on the first paint', function (): void {
    // The probes travel AFTER the first render (wire:init): eight checks with
    // timeouts must never hold the page hostage.
    $this->actingAs(integrationsAdmin())->get('/admin/integrations')
        ->assertOk()
        ->assertSee('<title>Integraciones</title>', false)
        ->assertSee(__('integrations.title'))
        ->assertSee(__('integrations.checking'))
        ->assertSee(__('integrations.names.whatsapp'))
        ->assertSee(__('integrations.names.n8n'))
        ->assertSee(__('integrations.names.openai'))
        ->assertSee(__('integrations.names.database'));
});

test('loading probes every integration and reports state, latency and detail', function (): void {
    fakeHealthyWorld();

    $this->actingAs(integrationsAdmin());

    $component = Livewire::test('configuration.integrations')->call('load');

    $statuses = $component->get('statuses');

    expect($statuses)->toHaveCount(count(app(IntegrationHealth::class)->keys()))
        ->and($statuses['whatsapp']['state'])->toBe(IntegrationState::Connected->value)
        ->and($statuses['whatsapp']['detail'])->toContain('2.1.1')
        ->and($statuses['whatsapp']['latency_ms'])->not->toBeNull()
        ->and($statuses['n8n']['state'])->toBe(IntegrationState::Connected->value)
        ->and($statuses['database']['state'])->toBe(IntegrationState::Connected->value)
        ->and($statuses['reverb']['state'])->toBe(IntegrationState::Connected->value);
});

test('a configured integration that stops answering reports failing, with a hint', function (): void {
    Http::fake([
        '*evolution-api*' => Http::response('boom', 500),
        '*' => Http::response([], 200),
    ]);

    app()->bind(IntegrationHealth::class, fn (): IntegrationHealth => new IntegrationHealth(
        tcpProbe: fn (): bool => true,
    ));

    $status = app(IntegrationHealth::class)->check('whatsapp');

    // Failing is the state that needs a hand: the hint says where to look.
    expect($status->state)->toBe(IntegrationState::Failing)
        ->and($status->hint)->toBe(__('integrations.hint.whatsapp'));
});

test('an unconfigured integration is off, which is a choice and not a fault', function (): void {
    config(['services.evolution.url' => null]);

    $status = app(IntegrationHealth::class)->check('whatsapp');

    expect($status->state)->toBe(IntegrationState::Off)
        ->and($status->hint)->toBeNull()
        ->and($status->detail)->toBe(__('integrations.detail.not_configured'));
});

test('the tests mailer keeps mail off instead of probing a server that is not there', function (): void {
    // phpunit forces MAIL_MAILER=array: there is no SMTP host worth probing.
    $status = app(IntegrationHealth::class)->check('mail');

    expect($status->state)->toBe(IntegrationState::Off)
        ->and($status->detail)->toContain('array');
});

test('a dead port turns the socket-probed integrations into failing', function (): void {
    app()->bind(IntegrationHealth::class, fn (): IntegrationHealth => new IntegrationHealth(
        tcpProbe: fn (): bool => false,
    ));

    $status = app(IntegrationHealth::class)->check('reverb');

    expect($status->state)->toBe(IntegrationState::Failing)
        ->and($status->hint)->toBe(__('integrations.hint.reverb'));
});

test('one integration can be re-probed alone after a fix', function (): void {
    fakeHealthyWorld();

    $this->actingAs(integrationsAdmin());

    $component = Livewire::test('configuration.integrations')->call('recheck', 'whatsapp');

    expect($component->get('statuses'))->toHaveKey('whatsapp')
        ->and($component->get('statuses'))->toHaveCount(1);
});

test('a key that is not an integration is ignored instead of probed', function (): void {
    $this->actingAs(integrationsAdmin());

    // `recheck` is reachable from the browser console: an arbitrary string
    // must die quietly, never reach a probe.
    Livewire::test('configuration.integrations')
        ->call('recheck', 'anything-else')
        ->assertSet('statuses', []);
});

test('the integrations option hangs from the settings item of the admin menu', function (): void {
    $this->seed(MenuSeeder::class);

    $settings = Menu::query()->where('label_key', 'menu.admin_settings')->sole();

    $this->assertDatabaseHas('menus', [
        'panel' => 'admin',
        'parent_id' => $settings->id,
        'label_key' => 'menu.admin_integrations',
        'route_name' => 'admin.integrations',
        'icon' => 'workflow',
    ]);

    // The icon has to exist in the central registry or <x-icon> draws nothing.
    expect(config('icons.workflow'))->not->toBeNull();
});

test('the dashboard tile for integrations is live and navigates to the screen', function (): void {
    $this->actingAs(integrationsAdmin())->get('/admin')
        ->assertOk()
        ->assertSee(route('admin.integrations'));
});
