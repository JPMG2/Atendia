<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);
});

test('a disconnected business sees the yellow chip, the short search and the connect action', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.topbar_disconnected'))
        ->assertSee(__('whatsapp.connect.cta'))
        ->assertAttribute('input[name="search"]', 'placeholder', __('menu.search_placeholder'))
        ->screenshot(filename: 'polish-customers-desktop');

    visit('/conversaciones')->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.connect.cta'))
        ->click('@theme-toggle')
        ->screenshot(filename: 'polish-conversations-phone-dark');

    visit('/clientes')->resize(1280, 900)
        ->click(__('whatsapp.topbar_disconnected'))
        ->assertPathIs('/whatsapp');
});

test('the pending invitation cancels with a red cross', function (): void {
    TeamInvitation::factory()->create(['business_id' => $this->business->id, 'email' => 'martin@shop.test']);

    visit('/equipo')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('martin@shop.test')
        ->assertPresent('button.icon-btn-danger[aria-label="'.__('team.people.cancel').'"]')
        ->screenshot(filename: 'polish-team-invitation');
});

test('my business answers from its own folder', function (): void {
    visit('/negocio')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.business.title'))
        ->screenshot(filename: 'polish-my-business');
});

test('a recent row on the home opens the thread it names', function (): void {
    $thread = Conversation::factory()->create([
        'business_id' => $this->business->id,
        'contact_name' => 'Carla Ruiz',
        'last_message_at' => now(),
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $this->business->id,
        'body' => '¿Tienen turnos para el jueves?',
    ]);

    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.recent.title'))
        ->screenshot(filename: 'polish-home-recent')
        ->click('Carla Ruiz')
        ->assertPathIs('/conversaciones')
        ->assertSee($thread->contact_phone)
        ->screenshot(filename: 'polish-home-recent-lands');

    // The cold path: the link a handoff ping mails to a phone, opened fresh.
    visit(route('conversations', ['hilo' => $thread->id]))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee($thread->contact_phone);
});

/**
 * 900px is the tightest the desktop layout gets: the 264px sidebar keeps its
 * width and the work area lives on what is left. The network picker is the
 * narrowest control of the panel, and a picker that cuts its own word is the
 * abbreviating this project forbids — measured on the input itself, because a
 * clipped placeholder still reads as present to `assertSee`.
 */
test('the network picker shows its whole word on a tablet', function (): void {
    SocialLink::factory()
        ->for($this->business, 'linkable')
        ->for(SocialNetwork::factory()->create(['name' => 'X (Twitter)']))
        ->create(['url' => 'https://x.com/clinicavida']);

    $page = visit(route('my-business.redes'))->resize(900, 1200);

    $page->assertNoJavaScriptErrors()
        ->click('@theme-toggle')
        ->screenshot(filename: 'polish-social-tablet');

    // scrollWidth beats clientWidth exactly when the text does not fit its box.
    $clipped = $page->script(
        "(() => { const i = document.querySelector('.config-social-row .combo-control .field-input');
                  return i.scrollWidth - i.clientWidth; })()"
    );

    expect((int) $clipped)->toBeLessThanOrEqual(0);
});

/**
 * The user who just registered reads these two screens before anything else,
 * and both printed their title over a void — no number, no sentence, no step.
 * Looked at on a phone in light, which is where a bare screen reads worst.
 */
test('a client whose business is not born yet reads the step on every screen', function (): void {
    $this->actingAs(User::factory()->create(['business_id' => null, 'email_verified_at' => now()])->refresh());

    visit(route('statistics'))->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('statistics.no_business.title'))
        ->assertSee(__('statistics.no_business.cta'))
        ->screenshot(filename: 'fresh-statistics-phone');

    visit(route('referrals'))->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('referrals.no_business.title'))
        ->screenshot(filename: 'fresh-referrals-phone')
        ->click(__('referrals.no_business.cta'))
        ->assertPathIs('/alta');
});

/**
 * A KPI row is read by scanning the numbers, not the labels: when one card's
 * label wraps to two lines and its neighbour's does not, the number floats and
 * the row loses the line the eye was following. Between 601 and 1100px every
 * label on the statistics screen wraps differently, so this is where it shows.
 */
test('the numbers of a KPI row sit on one line on a tablet', function (): void {
    $page = visit(route('statistics'))->resize(900, 1200);

    $page->assertNoJavaScriptErrors()
        ->click('@theme-toggle')
        ->screenshot(filename: 'polish-stats-tablet');

    // Cards that share a top are one row; their values must share one too.
    $offRows = $page->script(
        "(() => {
            const rows = {};
            document.querySelectorAll('.stat-card').forEach(card => {
                const top = Math.round(card.getBoundingClientRect().top);
                const value = card.querySelector('.stat-value').getBoundingClientRect().top;
                (rows[top] ??= new Set()).add(Math.round(value));
            });
            return Object.values(rows).filter(tops => tops.size > 1).length;
        })()"
    );

    expect((int) $offRows)->toBe(0);
});

/**
 * With five tabs of the panel open, the tab strip is the only place that says
 * which screen each one is. Measured in a real browser: the macro that was
 * supposed to name it never ran outside a page component, and the whole panel
 * said "AtendIa" for months without a single test noticing.
 */
test('the browser tab names the screen, and says the brand once', function (): void {
    $this->seed(MenuSeeder::class);

    visit(route('statistics'))->assertTitle(__('menu.statistics').' · '.config('app.name'));

    // A name that already carries the brand ("Gana con AtendIa") keeps it once.
    visit(route('referrals'))->assertTitle(__('menu.referrals'));
});
