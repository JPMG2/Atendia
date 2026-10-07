<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppLinkEvent;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlanSeeder::class);

    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
});

/*
|--------------------------------------------------------------------------
| Linking WhatsApp — the card both doors show
|--------------------------------------------------------------------------
| The panel screen and wizard step 5 render the same component, so these
| shots are the proof that a new signup and a working owner see one story.
| The fourth state, "unverified", only exists because a stamp outlived the
| pairing it described; it earns a shot of its own.
*/

function linkOwner(array $attributes = []): User
{
    $user = User::factory()->create();
    $user->assignRole('client');
    $user->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida', ...$attributes]))->save();

    test()->actingAs($user);

    return $user;
}

test('the panel screen invites an unlinked number to connect', function (): void {
    linkOwner();

    $page = visit('/whatsapp');

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.connect.title'))
        ->assertSee(__('whatsapp.connect.dedicated_title'))
        // The topbar must agree with the card: one screen, one claim.
        ->assertSee(__('whatsapp.topbar_disconnected'))
        ->screenshot(filename: 'whatsapp-panel-disconnected');

    $page->inDarkMode()->screenshot(filename: 'whatsapp-panel-disconnected-dark');
});

test('the panel screen holds together on a phone', function (): void {
    linkOwner();

    $page = visit('/whatsapp')->resize(390, 844);

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.connect.title'))
        ->screenshot(filename: 'whatsapp-panel-mobile');

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBe(0);
});

test('a silent bridge draws the unverified card instead of a green claim', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    linkOwner(['whatsapp_instance' => 'business-99', 'whatsapp_connected_at' => now()->subHours(6)]);

    visit('/whatsapp')
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.unverified.title'))
        ->assertDontSee(__('whatsapp.connected.title'))
        ->assertSee(__('whatsapp.topbar_unverified'))
        ->screenshot(filename: 'whatsapp-panel-unverified');
});

test('a linked number reads as connected on both the card and the pill', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([
        'instance' => ['state' => 'open'],
        '0' => ['ownerJid' => '5492995243890@s.whatsapp.net', 'profileName' => 'Laboratorio Vida', 'profilePicUrl' => null],
    ])]);

    // Older than the window, or the card would honestly report a clean week
    // it has no history for.
    $owner = linkOwner([
        'whatsapp_instance' => 'business-99',
        'whatsapp_connected_at' => now(),
        'created_at' => now()->subMonth(),
    ]);

    // One outage, three hours, and a reason only WhatsApp can give.
    WhatsAppLinkEvent::query()->create([
        'business_id' => $owner->business_id, 'connected' => false,
        'reason' => 'device_removed', 'occurred_at' => now()->subDays(2),
    ]);
    WhatsAppLinkEvent::query()->create([
        'business_id' => $owner->business_id, 'connected' => true,
        'reason' => 'paired', 'occurred_at' => now()->subDays(2)->addHours(3),
    ]);

    $page = visit('/whatsapp');

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.connected.title'))
        ->assertSee(__('whatsapp.topbar_connected'))
        ->assertSee('Laboratorio Vida')
        ->assertSee('5492995243890')
        ->assertSee(__('whatsapp.history.title', ['days' => 7]))
        ->assertSee(__('whatsapp.history.device_removed'))
        ->screenshot(filename: 'whatsapp-panel-connected');

    $page->inDarkMode()->screenshot(filename: 'whatsapp-panel-connected-dark');
});

test('a clean week says so instead of showing an empty figure', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['instance' => ['state' => 'open']])]);

    linkOwner(['whatsapp_instance' => 'business-99', 'whatsapp_connected_at' => now()]);

    visit('/whatsapp')
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.history.clean'))
        ->screenshot(filename: 'whatsapp-panel-clean-week');
});

test('the it-fell notice lands on a screen that already shows the qr', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([
        'instance' => ['state' => 'close'],
        'base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    ])]);

    linkOwner();

    visit('/whatsapp?conectar=1')
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.connect.qr_hint'))
        ->screenshot(filename: 'whatsapp-panel-qr-from-bell');
});

test('the topbar pill goes green on the page where the scan happened', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([
        'instance' => ['state' => 'open'],
        '0' => ['ownerJid' => '5492995243890@s.whatsapp.net', 'profileName' => 'Laboratorio Vida', 'profilePicUrl' => null],
    ])]);

    // No instance yet: the card asks the bridge nothing, so the page opens on
    // the honest "sin conectar" instead of linking itself behind the test.
    $owner = linkOwner();

    $page = visit('/whatsapp');
    $page->assertSee(__('whatsapp.topbar_disconnected'));

    // What the scan leaves behind, without the reload she should not need.
    $owner->business->forceFill([
        'whatsapp_instance' => 'business-99',
        'whatsapp_connected_at' => now(),
    ])->save();
    $owner->business->markLinkVerified();

    $page->script('Livewire.dispatch("whatsapp:connected")');

    // First the greeting, with the name the phone itself gave.
    $page->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.topbar_connected_as', ['name' => 'Laboratorio Vida']))
        ->assertDontSee(__('whatsapp.topbar_disconnected'))
        ->screenshot(filename: 'whatsapp-pill-greeting');

    // The same moment in dark: the avatar is the only new ink in the topbar.
    // The class is set by hand because inDarkMode() leaves this page in light.
    $page->script('document.documentElement.classList.add("dark")');
    $page->script('Livewire.dispatch("whatsapp:connected")');
    $page->assertSee(__('whatsapp.topbar_connected_as', ['name' => 'Laboratorio Vida']))
        ->screenshot(filename: 'whatsapp-pill-greeting-dark');

    // Then it retires on its own, five seconds in, with nobody clicking.
    $page->wait(6)
        ->assertSee(__('whatsapp.topbar_connected'))
        ->screenshot(filename: 'whatsapp-pill-live-green');
});

test('wizard step 5 carries the same linking card under the numbers', function (): void {
    linkOwner();

    $page = visit('/alta');

    $page->assertNoJavaScriptErrors()
        ->click('text='.__('wizard.steps.5.label').' >> visible=true')
        ->assertSee(__('wizard.steps.5.heading'))
        ->assertSee(__('wizard.whatsapp.link_heading'))
        ->assertSee(__('whatsapp.connect.cta'))
        ->screenshot(filename: 'whatsapp-wizard-step');

    $page->resize(390, 844)->screenshot(filename: 'whatsapp-wizard-step-mobile');

    // Scoped to the card on purpose: the wizard's own 4-tab nav already
    // overflows 390px, and that debt is not this screen's to answer for.
    expect((int) $page->script(<<<'JS'
        (() => {
            const card = document.querySelector('[data-testid="whatsapp-link"]');
            return card.scrollWidth - card.clientWidth;
        })()
    JS))->toBe(0);
});
