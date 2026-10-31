<?php

declare(strict_types=1);

use App\Actions\Admin\ReadPlatformWhatsApp;
use App\Enums\PlatformWhatsAppState;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Company;
use App\Models\Menu;
use App\Models\PlatformSensor;
use App\Models\SeasonalWindow;
use App\Models\User;
use App\Models\WhatsappVariant;
use App\Services\PlatformSensors;
use App\Services\PlatformWhatsApp;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The platform's own WhatsApp line, in the admin
|--------------------------------------------------------------------------
| Aliadito says its own things (access codes, alerts, summaries) through ONE
| instance that belongs to no business. This screen shows what it is doing and
| links it with a QR; seeing it and linking it are two different keys.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.instance', 'aliadito-plataforma');

    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  list<string>  $instances
 * @param  array<string, mixed>  $row
 */
function platformBridgeSays(string $state = 'open', array $instances = ['aliadito-plataforma'], array $row = []): void
{
    // A fresh client each time: a second fake would sit BEHIND the first, and what was recorded would linger.
    Http::swap(new HttpFactory);

    Http::fake([
        'http://evolution.test/instance/fetchInstances*' => fn (Request $request) => Http::response(
            str_contains($request->url(), 'instanceName=')
                ? [array_replace(['name' => 'aliadito-plataforma', 'ownerJid' => '5492995243890@s.whatsapp.net'], $row)]
                : array_map(fn (string $name): array => ['name' => $name], $instances),
        ),
        'http://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => $state]]),
        'http://evolution.test/instance/connect/*' => Http::response(['base64' => 'data:image/png;base64,QUFB']),
        'http://evolution.test/instance/create' => Http::response(['status' => 'SUCCESS']),
        'http://evolution.test/instance/logout/*' => Http::response(['status' => 'SUCCESS']),
    ]);
}

function platformAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return tap($user->refresh(), fn (User $user) => test()->actingAs($user));
}

/** Somebody who may LOOK at the line and nothing else. */
function lineViewer(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(['access-admin-panel', 'integrations.view']);

    return tap($user->refresh(), fn (User $user) => test()->actingAs($user));
}

describe('who gets in', function (): void {
    test('a guest is sent to login and a client is refused', function (): void {
        $this->get(route('admin.platform-whatsapp'))->assertRedirect('/login');

        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole('client');

        $this->actingAs($client)->get(route('admin.platform-whatsapp'))->assertForbidden();
    });

    test('support, who has no integrations key, is refused too', function (): void {
        $this->actingAs(platformAdmin('support'))->get(route('admin.platform-whatsapp'))->assertForbidden();
    });

    test('whoever may look can open it, and the admin holds both keys while support holds neither', function (): void {
        lineViewer();

        $this->get(route('admin.platform-whatsapp'))->assertOk()->assertSee(__('platform_whatsapp.title'));

        expect(Permission::findByName('integrations.manage'))->not->toBeNull()
            ->and(User::role('admin')->count())->toBe(0);

        $admin = platformAdmin();
        $support = User::factory()->create();
        $support->assignRole('support');

        expect($admin->can('integrations.manage'))->toBeTrue()
            ->and($support->can('integrations.view'))->toBeFalse()
            ->and($support->can('integrations.manage'))->toBeFalse();
    });

    test('it sits in the Plataforma branch of the admin menu, behind the view key', function (): void {
        $this->seed(MenuSeeder::class);

        $row = Menu::query()->where('route_name', 'admin.platform-whatsapp')->firstOrFail();

        expect($row->parent?->label_key)->toBe('menu.admin_platform')
            ->and($row->permission)->toBe('integrations.view')
            ->and(__($row->label_key))->toBe('Línea de WhatsApp');
    });
});

describe('what it says', function (): void {
    test('it paints before asking the bridge, so a slow bridge never holds the page', function (): void {
        Http::fake();
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')->assertSee(__('platform_whatsapp.status.checking'));

        Http::assertNothingSent();
    });

    test('a linked line says connected, with its number and instance, and offers to unlink it', function (): void {
        platformBridgeSays('open');
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('check')
            ->assertSee(__('platform_whatsapp.status.connected'))
            ->assertSee('+5492995243890')
            ->assertSee('aliadito-plataforma')
            ->assertSee(__('platform_whatsapp.unlink.action'))
            ->assertDontSee(__('platform_whatsapp.link.start'));
    });

    test('a closed line says since when and why, only when the board agrees with the bridge', function (): void {
        platformBridgeSays('close', row: ['disconnectionObject' => json_encode(['error' => ['data' => ['attrs' => ['type' => 'device_removed']]]])]);
        PlatformSensor::query()->create(['sensor_key' => 'platform_whatsapp', 'group' => 'system', 'status' => 'crit', 'value' => 'close', 'changed_at' => '2026-10-21 23:14:44', 'checked_at' => now()]);
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('check')
            ->assertSee(__('platform_whatsapp.status.closed'))
            ->assertSee(__('platform_whatsapp.reason.device_removed'))
            ->assertSee('21/10')
            ->assertSee(__('platform_whatsapp.notes.down'));
    });

    test('a date the board does not stand behind is not shown', function (): void {
        platformBridgeSays('open');
        PlatformSensor::query()->create(['sensor_key' => 'platform_whatsapp', 'group' => 'system', 'status' => 'crit', 'value' => 'close', 'changed_at' => '2026-10-21 23:14:44', 'checked_at' => now()]);
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('check')
            ->assertSee(__('platform_whatsapp.status.connected'))
            ->assertDontSee(__('platform_whatsapp.rows.since_connected'));
    });

    test('an instance the bridge does not run yet says it is not created', function (): void {
        platformBridgeSays('close', instances: ['ai_project']);
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')->call('check')->assertSee(__('platform_whatsapp.status.missing'));
    });

    test('a bridge that does not answer claims nothing about the line', function (): void {
        Http::fake(fn () => throw new ConnectionException('down'));
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('check')
            ->assertSee(__('platform_whatsapp.status.unreachable'))
            ->assertSee(__('platform_whatsapp.notes.unreachable'))
            ->assertDontSee(__('platform_whatsapp.status.connected'));
    });

    test('a request nobody expected is reported and the screen still draws, saying the line cannot be read', function (): void {
        // No fake at all: the test client refuses stray requests, which is an unexpected failure, not a refused connection.
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('check')
            ->assertOk()
            ->assertSee(__('platform_whatsapp.status.unreachable'));
    });

    test('with no instance configured it says so and talks to nobody', function (): void {
        config()->set('services.evolution.instance', '');
        Http::fake();
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('check')
            ->assertSee(__('platform_whatsapp.status.not_configured'))
            ->assertSee(__('platform_whatsapp.notes.not_configured'));

        Http::assertNothingSent();
    });
});

describe('linking it', function (): void {
    test('the first time the instance is created and a QR is shown; the next time it is not created again', function (): void {
        platformBridgeSays('close', instances: ['ai_project']);
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('link')
            ->assertSet('qr', 'data:image/png;base64,QUFB')
            ->assertSee(__('platform_whatsapp.link.steps.scan'));

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/instance/create') && $request['instanceName'] === 'aliadito-plataforma');

        platformBridgeSays('close');
        Livewire::test('admin.platform-whatsapp.index')->call('link')->assertSet('qr', 'data:image/png;base64,QUFB');

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/instance/create'));
    });

    test('when the phone scans it the QR goes away by itself on the next look', function (): void {
        platformBridgeSays('close');
        platformAdmin();

        $screen = Livewire::test('admin.platform-whatsapp.index')->call('link')->assertSet('qr', 'data:image/png;base64,QUFB');

        platformBridgeSays('open');
        $screen->call('check')->assertSet('qr', null)->assertSee(__('platform_whatsapp.status.connected'));
    });

    test('a QR older than its life is replaced with a fresh one', function (): void {
        platformBridgeSays('close');
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->set('qr', 'data:image/png;base64,VIEJO')
            ->set('qrAt', time() - 60)
            ->call('check')
            ->assertSet('qr', 'data:image/png;base64,QUFB');
    });

    test('a bridge that fails while linking says so and keeps no QR', function (): void {
        Http::fake(fn () => throw new ConnectionException('down'));
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('link')
            ->assertSet('qr', null)
            ->assertDispatched('notify', type: 'error', message: __('platform_whatsapp.link.failed'));
    });

    test('with no instance configured nothing is created and it says so', function (): void {
        config()->set('services.evolution.instance', '');
        Http::fake();
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')->call('link')->assertSet('qr', null)->assertDispatched('notify', type: 'error');

        Http::assertNothingSent();
    });

    test('whoever may only look cannot link', function (): void {
        platformBridgeSays('close');
        lineViewer();

        Livewire::test('admin.platform-whatsapp.index')->call('link')->assertForbidden();

        Http::assertNothingSent();
    });
});

describe('unlinking it', function (): void {
    test('it closes the session of the platform instance, and only that one', function (): void {
        platformBridgeSays('open');
        platformAdmin();

        Livewire::test('admin.platform-whatsapp.index')
            ->call('unlink')
            ->assertDispatched('notify', type: 'success', message: __('platform_whatsapp.unlink.done'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/instance/logout/aliadito-plataforma'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/delete/'));
    });

    test('whoever may only look cannot unlink', function (): void {
        platformBridgeSays('open');
        lineViewer();

        Livewire::test('admin.platform-whatsapp.index')->call('unlink')->assertForbidden();

        Http::assertNothingSent();
    });
});

function lineSensor(string $status, int $minutesAgo = 1, ?int $changedMinutesAgo = null): void
{
    PlatformSensor::query()->create([
        'sensor_key' => 'platform_whatsapp',
        'group' => 'system',
        'status' => $status,
        'value' => $status === 'ok' ? 'open' : 'close',
        'checked_at' => now()->subMinutes($minutesAgo),
        'changed_at' => now()->subMinutes($changedMinutesAgo ?? $minutesAgo),
    ]);
}

describe('how long it has been down', function (): void {
    test('the pill says for how long the line has been down, from the board\'s own date', function (): void {
        lineSensor('crit', changedMinutesAgo: 125);
        lineViewer();

        Livewire::test('whatsapp.platform-pill')
            ->assertSeeHtml('data-testid="platform-pill-since"')
            ->assertSee('2');
    });

    test('a date the board does not stand behind is not shown beside what the screen just read', function (): void {
        lineSensor('ok', changedMinutesAgo: 600);
        PlatformWhatsApp::remember(PlatformWhatsAppState::Closed, CarbonImmutable::now());
        lineViewer();

        Livewire::test('whatsapp.platform-pill')
            ->assertSeeHtml('data-testid="platform-pill-down"')
            ->assertDontSeeHtml('platform-pill-since');
    });

    test('the home warns while the line is known to be down, with the way back only for who may link it', function (): void {
        lineSensor('crit', changedMinutesAgo: 90);
        platformAdmin();

        Livewire::test('admin.home.index')
            ->assertSeeHtml('data-testid="platform-line-down"')
            ->assertSee(__('admin.home.line_down.link'));

        $this->actingAs(lineViewer());

        Livewire::test('admin.home.index')
            ->assertSeeHtml('data-testid="platform-line-down"')
            ->assertDontSee(__('admin.home.line_down.link'));
    });

    test('the home says nothing when the line is fine, unverified or the viewer cannot look at it', function (): void {
        lineSensor('ok');
        platformAdmin();

        Livewire::test('admin.home.index')->assertDontSeeHtml('platform-line-down');

        PlatformSensor::query()->delete();
        lineSensor('crit', minutesAgo: 30);

        Livewire::test('admin.home.index')->assertDontSeeHtml('platform-line-down');

        PlatformSensor::query()->delete();
        lineSensor('crit');
        $this->actingAs(platformAdmin('support'));

        Livewire::test('admin.home.index')->assertDontSeeHtml('platform-line-down');
    });
});

describe('the topbar pill', function (): void {
    test('it says connected, naming the brand, while the board reads the line green', function (): void {
        Http::fake();
        lineSensor('ok');
        lineViewer();

        Livewire::test('whatsapp.platform-pill')
            ->assertSee(__('platform_whatsapp.topbar.connected', ['brand' => Company::brand()]))
            ->assertSeeHtml('data-testid="platform-pill-connected"');

        Http::assertNothingSent();
    });

    test('a closed session is a warning that links to the line screen', function (): void {
        lineSensor('crit');
        lineViewer();

        Livewire::test('whatsapp.platform-pill')
            ->assertSeeHtml('data-testid="platform-pill-down"')
            ->assertSee(__('platform_whatsapp.topbar.disconnected', ['brand' => Company::brand()]))
            ->assertSeeHtml(route('admin.platform-whatsapp'));
    });

    test('a green nobody refreshed, or no reading at all, is never shown as connected', function (): void {
        lineViewer();

        Livewire::test('whatsapp.platform-pill')->assertSeeHtml('data-testid="platform-pill-unverified"');

        lineSensor('ok', minutesAgo: 30);

        Livewire::test('whatsapp.platform-pill')
            ->assertSeeHtml('data-testid="platform-pill-unverified"')
            ->assertDontSeeHtml('platform-pill-connected');
    });

    test('what the screen just learned from the bridge wins over an older reading', function (): void {
        lineSensor('crit', minutesAgo: 3);
        platformBridgeSays('open');
        platformAdmin();

        Livewire::test('whatsapp.platform-pill')->assertSeeHtml('data-testid="platform-pill-down"');

        Livewire::test('admin.platform-whatsapp.index')->call('check');

        Livewire::test('whatsapp.platform-pill')->assertSeeHtml('data-testid="platform-pill-connected"');
    });

    test('whoever cannot see the line does not get the pill', function (): void {
        lineSensor('ok');
        $this->actingAs(platformAdmin('support'));

        Livewire::test('whatsapp.platform-pill')->assertDontSeeHtml('conn-pill ');
    });

    test('the admin topbar carries it and the client topbar does not', function (): void {
        lineSensor('ok');
        platformAdmin();

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('platform-pill-connected', false);

        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole('client');

        $this->actingAs($client)->get(route('dashboard'))->assertDontSee('platform-pill-', false);
    });
});

describe('the line only speaks', function (): void {
    test('a message that arrives on the platform instance is not a customer and is never queued', function (): void {
        Queue::fake();
        config()->set('services.evolution.webhook_secret', 'test-secret');

        $this->postJson('/api/webhooks/evolution', [
            'event' => 'messages.upsert',
            'instance' => 'aliadito-plataforma',
            'data' => ['key' => ['remoteJid' => '5491122334455@s.whatsapp.net', 'fromMe' => false, 'id' => 'M1'], 'pushName' => 'Carla', 'message' => ['conversation' => 'gracias']],
        ], ['X-Webhook-Secret' => 'test-secret'])->assertOk()->assertJson(['handled' => false]);

        Queue::assertNotPushed(ProcessIncomingWhatsAppMessage::class);
    });
});

describe('the number every "write to us" dials', function (): void {
    test('it is the line\'s own number as the bridge reports it, never a phone typed in Compañía', function (): void {
        Company::factory()->create(['phone' => '+58-0424-8131833']);
        platformBridgeSays('open');

        app(ReadPlatformWhatsApp::class)->handle();

        expect(Company::current()->whatsapp_line)->toBe('5492995243890')
            ->and(Company::whatsapp())->toBe('5492995243890');
        $this->get('/')->assertSee('wa.me/5492995243890', false)->assertDontSee('wa.me/58', false);
    });

    test('the board\'s periodic probe keeps it too, so a re-linked number reaches the site without anyone opening a screen', function (): void {
        Company::factory()->create(['whatsapp_line' => '5491100000000']);
        platformBridgeSays('open');

        app(PlatformSensors::class)->read();

        expect(Company::current()->whatsapp_line)->toBe('5492995243890');
    });

    test('a line that dropped stops being dialled at once: the site falls back to the typed phone', function (): void {
        Company::factory()->create(['phone' => '+54 9 11 5555-1234', 'whatsapp_line' => '5492995243890']);

        PlatformWhatsApp::remember(PlatformWhatsAppState::Connected, CarbonImmutable::now());
        expect(Company::whatsapp())->toBe('5492995243890');

        PlatformWhatsApp::remember(PlatformWhatsAppState::Closed, CarbonImmutable::now());
        expect(Company::whatsapp())->toBe('5491155551234');
        $this->get('/')->assertSee('wa.me/5491155551234', false)->assertDontSee('wa.me/5492995243890', false);
    });

    test('a new support ticket tells the team\'s own phone, never the line that sends the notice', function (): void {
        Company::factory()->create(['phone' => '+54 9 11 5555-1234', 'whatsapp_line' => '5492995243890']);
        PlatformWhatsApp::remember(PlatformWhatsAppState::Connected, CarbonImmutable::now());

        expect(Company::teamWhatsapp())->toBe('5491155551234')
            ->and(Company::whatsapp())->toBe('5492995243890');
    });

    test('the seasonal catalog names the number a blank variant really inherits', function (): void {
        Company::factory()->create(['phone' => '+54 9 11 5555-1234', 'whatsapp_line' => '5492995243890']);
        PlatformWhatsApp::remember(PlatformWhatsAppState::Connected, CarbonImmutable::now());
        WhatsappVariant::factory()->create(['seasonal_window_id' => SeasonalWindow::factory()->create()->id, 'phone' => null]);

        expect((new WhatsappVariant)->catalogRows()->first()['inherits_note'])->toContain('5492995243890')->not->toContain('5555');
    });

    test('a season\'s own number still speaks for its days; the typed phone only while the line never answered', function (): void {
        $company = Company::factory()->create(['phone' => '+54 9 11 5555-1234', 'whatsapp_line' => null]);
        expect(Company::whatsapp())->toBe('5491155551234');

        $company->forceFill(['whatsapp_line' => '5492995243890'])->save();
        Company::forgetReach();
        PlatformWhatsApp::remember(PlatformWhatsAppState::Connected, CarbonImmutable::now());
        expect(Company::whatsapp())->toBe('5492995243890');

        $season = SeasonalWindow::factory()->create();
        WhatsappVariant::factory()->create(['seasonal_window_id' => $season->id, 'phone' => '+54 9 351 600-0000']);
        Company::forgetReach();
        expect(Company::whatsapp())->toBe('5493516000000');
    });
});
