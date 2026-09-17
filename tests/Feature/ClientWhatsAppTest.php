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
        ->assertSee(__('whatsapp.connect.privacy'));
});

test('a connected business sees its state and since when', function (): void {
    $this->actingAs(whatsappScreenClient([
        'whatsapp_connected_at' => '2026-09-17 10:00:00',
    ]));

    $this->get(route('whatsapp'))
        ->assertSee(__('whatsapp.connected.title'))
        ->assertSee('17/09/2026');
});

test('connecting provisions the instance, points the webhook home and shows the qr', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['base64' => 'data:image/png;base64,QR'])]);

    $user = whatsappScreenClient();
    $this->actingAs($user);

    livewire('client.whatsapp-connect')
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

    livewire('client.whatsapp-connect')
        ->call('connect')
        ->assertSet('linking', true);

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/instance/create'));
});

test('a dead bridge turns into a toast, not a crash', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    $this->actingAs(whatsappScreenClient());

    livewire('client.whatsapp-connect')
        ->call('connect')
        ->assertSet('linking', false)
        ->assertDispatched('notify');
});

test('the poll flips to connected when the webhook has stamped the column', function (): void {
    Http::fake();

    $user = whatsappScreenClient(['whatsapp_instance' => 'business-99']);
    $this->actingAs($user);

    $component = livewire('client.whatsapp-connect')->set('linking', true);

    // The connection webhook stamps the column out of band.
    $user->business->update(['whatsapp_connected_at' => now()]);

    $component->call('checkLink')
        ->assertSet('linking', false)
        ->assertSet('qr', null)
        ->assertDispatched('notify');
});
