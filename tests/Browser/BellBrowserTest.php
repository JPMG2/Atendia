<?php

declare(strict_types=1);

use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\PanelNotification;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The bell, looked at — the dot that was always lit is gone
|--------------------------------------------------------------------------
| A count that only appears when something is unread, a panel that reads
| in both themes and on a phone, and a row whose read mark survives the
| click. Measured and captured: a green assertion does not prove a layout.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);
});

/** One row of each kind, so the capture shows every tint at once. */
function seedBellInbox(Business $business): void
{
    PanelNotification::raise($business, PanelNotificationType::CustomerWaiting, 'waiting:1', ['name' => 'Carla Ruiz', 'minutes' => 25], '/conversaciones');
    PanelNotification::raise($business, PanelNotificationType::HandedToTeam, 'handoff:2', ['name' => 'Diego Paz'], '/conversaciones');
    PanelNotification::raise($business, PanelNotificationType::AppointmentBooked, 'booking:3', ['name' => 'Ana Sosa', 'when' => '30/09 14:30', 'service' => 'Consulta'], '/agenda');
    PanelNotification::raise($business, PanelNotificationType::WhatsAppDisconnected, 'whatsapp-down:today', ['at' => '03:12'], '/whatsapp');
}

test('the bell sits on the same line as its topbar siblings', function (): void {
    $page = visit('/dashboard')->resize(1280, 900)->assertNoJavaScriptErrors();

    // Measured, not eyeballed: the owner caught it off by a few pixels, which
    // is exactly the amount a screenshot hides.
    $drift = $page->script(
        'Math.round(document.querySelector("[data-testid=bell]").getBoundingClientRect().top'
        .' - document.querySelector("[data-testid=theme-toggle]").getBoundingClientRect().top)'
    );

    expect((int) (is_array($drift) ? end($drift) : $drift))->toBe(0);
});

test('an empty inbox leaves the bell without a count', function (): void {
    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertPresent('@bell')
        ->assertMissing('@bell-count')
        ->screenshot(filename: 'bell-empty-desktop');
});

test('the bell counts the unread and opens the inbox in both themes', function (): void {
    seedBellInbox($this->business);

    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSeeIn('[data-testid="bell-count"]', '4')
        ->click('@bell')
        ->assertSee(__('bell.title'))
        ->assertSee('Carla Ruiz espera respuesta desde hace 25 minutos')
        ->assertSee('Turno nuevo de Ana Sosa: 30/09 14:30, Consulta')
        ->assertSee(__('bell.mark_all'))
        ->screenshot(filename: 'bell-panel-desktop-light');

    // The theme is flipped BEFORE opening: the panel is modal and covers the
    // topbar, so the toggle underneath is unreachable while it is open.
    visit('/dashboard')->resize(1280, 900)
        ->click('@theme-toggle')
        ->click('@bell')
        ->assertSee('Carla Ruiz espera respuesta desde hace 25 minutos')
        ->screenshot(filename: 'bell-panel-desktop-dark');
});

test('the inbox reads on a phone without scrolling sideways', function (): void {
    seedBellInbox($this->business);

    $page = visit('/dashboard')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->click('@bell')
        ->assertSee('Carla Ruiz espera respuesta desde hace 25 minutos')
        ->screenshot(filename: 'bell-panel-phone');

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) (is_array($overflow) ? end($overflow) : $overflow))->toBeLessThanOrEqual(0);
});

test('a repeated kind reads as one counted row', function (): void {
    foreach (['Carla Ruiz', 'Diego Paz', 'Ana Sosa'] as $index => $name) {
        PanelNotification::raise(
            $this->business,
            PanelNotificationType::CustomerWaiting,
            'waiting:'.$index,
            ['name' => $name, 'minutes' => 20],
            '/conversaciones',
        );
    }

    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('@bell')
        ->assertSee('3 clientes esperan respuesta')
        ->screenshot(filename: 'bell-bundled');
});

test('on a phone a waiting row opens the chat, not the list', function (): void {
    $thread = Conversation::factory()->for($this->business)->create(['contact_name' => 'Carla Ruiz']);

    PanelNotification::raise(
        $this->business,
        PanelNotificationType::CustomerWaiting,
        'waiting:'.$thread->id,
        ['name' => 'Carla Ruiz', 'minutes' => 25],
        route('conversations', ['hilo' => $thread->id]),
    );

    visit('/dashboard')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->click('@bell')
        ->click('@bell-row-customer_waiting')
        ->assertPathIs('/conversaciones')
        // The thread pane, not the inbox: at phone width the list hides itself
        // whenever a thread is selected.
        ->assertSee('Carla Ruiz')
        ->screenshot(filename: 'bell-phone-lands-on-thread');
});

test('the payment history offers its years and filters to one', function (): void {
    Payment::factory()->for($this->business)->create(['created_at' => '2026-03-04 12:00:00']);
    Payment::factory()->for($this->business)->create(['created_at' => '2025-07-09 12:00:00']);

    visit('/pagos')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        // The picker's own input, not its placeholder: a placeholder is an
        // attribute, and assertSee only reads text on the page.
        ->assertPresent('[name="history_year"]')
        ->assertSee('2026')
        ->screenshot(filename: 'payments-year-filter-phone');
});

test('the AI button on Servicios is wired to something', function (): void {
    Service::factory()->for($this->business)->create(['description' => null]);

    visit('/servicios')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertPresent('@ai-banner-action')
        ->screenshot(filename: 'services-ai-button');
});

/*
 * The other two banners were notes while nothing ran behind them. Products now
 * offers the writer the services screen already had, and Identity offers the
 * one written for it, so all three banners carry a button that answers.
 */
test('the products and identity banners offer what they can actually do', function (): void {
    // A product has to exist: with an empty catalog the screen shows its
    // offer-empty block instead, and the banner is not on screen at all.
    Product::factory()->for($this->business)->create();

    visit('/productos')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('¿No sabes cómo redactar las descripciones?')
        ->assertPresent('@ai-banner-action')
        ->screenshot(filename: 'ai-banner-products-wired');

    visit('/negocio/identidad')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertSee('¿Te cuesta describir tu negocio?')
        ->assertPresent('@ai-banner-action')
        ->screenshot(filename: 'ai-banner-identity-wired-phone');
});

test('the identity field carries its own offer to write, on both widths', function (): void {
    visit('/negocio/identidad')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="identity-bio-write"]')
        ->screenshot(filename: 'identity-field-write-action');

    visit('/negocio/identidad')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="identity-bio-write"]')
        ->screenshot(filename: 'identity-field-write-action-phone');
});

test('a booking with no service reads as a whole sentence', function (): void {
    PanelNotification::raise(
        $this->business,
        PanelNotificationType::AppointmentBooked,
        'booking:plain',
        ['name' => 'Ana Sosa', 'when' => '06/10 09:00', 'service' => ''],
        '/agenda',
    );

    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('@bell')
        ->assertSee('Turno nuevo de Ana Sosa: 06/10 09:00')
        ->screenshot(filename: 'bell-booking-no-service');
});

test('the owner sees what her team taught, already live', function (): void {
    PanelNotification::raise(
        $this->business,
        PanelNotificationType::TaughtByTeammate,
        'taught:1',
        ['who' => 'Sofía Ramírez', 'question' => '¿Atienden los sábados?'],
        '/asistente',
    );

    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('@bell')
        ->assertSee('Sofía Ramírez le enseñó a tu asistente a responder: ¿Atienden los sábados?')
        ->screenshot(filename: 'bell-taught-by-teammate');
});

test('the settings screen lists one switch per kind', function (): void {
    visit('/ajustes/avisos')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('bell.settings.title'))
        ->assertSee(__('bell.settings.kinds.customer_waiting'))
        ->assertSee(__('bell.settings.kinds.whatsapp_disconnected'))
        ->screenshot(filename: 'bell-settings');
});

test('opening a row lands on its screen and clears one from the count', function (): void {
    seedBellInbox($this->business);

    visit('/dashboard')->resize(1280, 900)
        ->click('@bell')
        ->click('@bell-row-appointment_booked')
        ->assertPathIs('/agenda')
        ->assertSeeIn('[data-testid="bell-count"]', '3')
        ->screenshot(filename: 'bell-row-lands');
});
