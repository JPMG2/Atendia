<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.webhook_url', 'http://atendia-app/api/webhooks/evolution');
    config()->set('services.evolution.webhook_secret', 'shared-secret');

    $this->seed(RolesAndPermissionsSeeder::class);
});

function whatsappScreenClient(array $attributes = []): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create($attributes))->save();

    return $user;
}

/** The bridge's verdict on the link, which is what the screen may claim. */
function bridgeSays(string $state): void
{
    Http::fake(['http://evolution.test/*' => Http::response([
        'instance' => ['state' => $state],
        'base64' => 'data:image/png;base64,QR',
    ])]);
}

test('guests are sent to the login', function (): void {
    $this->get(route('whatsapp'))->assertRedirect(route('login'));
});

test('a client with no business is pointed at the wizard first', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('whatsapp'))
        ->assertSuccessful()
        ->assertSee(__('whatsapp.no_business'))
        ->assertSee(route('onboarding'), false);
});

test('an unconnected business sees the steps and the connect call', function (): void {
    $this->actingAs(whatsappScreenClient());

    $this->get(route('whatsapp'))
        ->assertSuccessful()
        ->assertSee(__('whatsapp.connect.title'))
        ->assertSee(__('whatsapp.connect.cta'))
        ->assertSee(__('whatsapp.connect.dedicated_title'))
        ->assertSee(__('whatsapp.connect.tip'))
        ->assertSee(__('whatsapp.connect.privacy'));
});

test('a connected business sees its state and since when', function (): void {
    bridgeSays('open');

    $this->actingAs(whatsappScreenClient([
        'whatsapp_instance' => 'business-99',
        'whatsapp_connected_at' => '2026-09-17 10:00:00',
    ]));

    $this->get(route('whatsapp'))
        ->assertSee(__('whatsapp.connected.title'))
        ->assertSee('17/09/2026');
});

test('the connected card names the number that actually got linked', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([
        'instance' => ['state' => 'open'],
        '0' => ['ownerJid' => '5492995243890@s.whatsapp.net', 'profileName' => 'Fortin IA', 'profilePicUrl' => null],
    ])]);

    $this->actingAs(whatsappScreenClient([
        'whatsapp_instance' => 'business-99',
        'whatsapp_connected_at' => now(),
    ]));

    $this->get(route('whatsapp'))
        ->assertSee('Fortin IA')
        ->assertSee('5492995243890')
        ->assertSee(__('whatsapp.connected.identity_hint'));
});

test('arriving from the it-fell notice already asks for the qr', function (): void {
    bridgeSays('close');

    $user = whatsappScreenClient();
    $this->actingAs($user);

    $this->get(route('whatsapp', ['conectar' => 1]))
        ->assertSuccessful()
        ->assertSee(__('whatsapp.connect.qr_hint'));

    expect($user->business->refresh()->whatsapp_instance)->not->toBeNull();
});

test('a silent bridge is drawn as unverified, never as connected', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    $this->actingAs(whatsappScreenClient([
        'whatsapp_instance' => 'business-99',
        'whatsapp_connected_at' => '2026-09-17 10:00:00',
    ]));

    $this->get(route('whatsapp'))
        ->assertSee(__('whatsapp.unverified.title'))
        ->assertDontSee(__('whatsapp.connected.title'));
});

test('a stamp the bridge contradicts is cleared, not shown', function (): void {
    bridgeSays('close');

    $user = whatsappScreenClient([
        'whatsapp_instance' => 'business-99',
        'whatsapp_connected_at' => '2026-09-17 10:00:00',
    ]);
    $this->actingAs($user);

    $this->get(route('whatsapp'))->assertSee(__('whatsapp.connect.title'));

    expect($user->business->refresh()->whatsapp_connected_at)->toBeNull();
});

test('connecting provisions the instance, points the webhook home and shows the qr', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['base64' => 'data:image/png;base64,QR'])]);

    $user = whatsappScreenClient();
    $this->actingAs($user);

    livewire('whatsapp.link')
        ->call('connect')
        ->assertSet('linking', true)
        ->assertSet('qr', 'data:image/png;base64,QR');

    $instance = 'business-'.$user->business->id;

    expect($user->business->refresh()->whatsapp_instance)->toBe($instance);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/instance/create')
        && $request['instanceName'] === $instance);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), "/webhook/set/{$instance}")
        && $request['webhook']['events'] === ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']);
});

test('a half-done linking resumes without provisioning again', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['base64' => 'data:image/png;base64,QR'])]);

    $this->actingAs(whatsappScreenClient(['whatsapp_instance' => 'business-99']));

    livewire('whatsapp.link')
        ->call('connect')
        ->assertSet('linking', true);

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/instance/create'));
});

test('a dead bridge turns into a toast, not a crash', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    $this->actingAs(whatsappScreenClient());

    livewire('whatsapp.link')
        ->call('connect')
        ->assertSet('linking', false)
        ->assertDispatched('notify');
});

test('the poll flips to connected when the bridge says the number answers', function (): void {
    bridgeSays('open');

    $user = whatsappScreenClient(['whatsapp_instance' => 'business-99']);
    $this->actingAs($user);

    livewire('whatsapp.link')
        ->set('linking', true)
        ->call('checkLink')
        ->assertSet('linking', false)
        ->assertSet('qr', null)
        ->assertDispatched('notify')
        ->assertDispatched('whatsapp:connected');

    // The poll is also what stamps the column: linking no longer waits on
    // a webhook that a restarted bridge may never send.
    expect($user->business->refresh()->whatsapp_connected_at)->not->toBeNull();
});

test('the poll keeps waiting while the bridge has not paired yet', function (): void {
    bridgeSays('connecting');

    $this->actingAs(whatsappScreenClient(['whatsapp_instance' => 'business-99']));

    livewire('whatsapp.link')
        ->set('linking', true)
        ->call('checkLink')
        ->assertSet('linking', true)
        ->assertNotDispatched('whatsapp:connected');
});
