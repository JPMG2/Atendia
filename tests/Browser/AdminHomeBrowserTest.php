<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Models\Payment;
use App\Models\PlatformFigure;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The admin's home with a working day behind it: receipts waiting, renewals
 * due and two businesses that did not pay. Every figure on it is counted from
 * these rows, so the test asserts the number AND the row it came from.
 */
beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $paying = Business::factory()->count(3)->create();

    $paying->each(function (Business $business): void {
        Subscription::factory()->for($business)->create([
            'plan' => 'negocio',
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_ends_at' => now()->addDays(3),
        ]);
    });

    // A receipt waiting, and one that pays for a different plan: the second is
    // inside the queue AND inside the plan-change count.
    Payment::factory()->for($paying->first())->create([
        'status' => 'pending',
        'plan' => 'negocio',
        'subscription_id' => $paying->first()->subscription?->id,
    ]);

    $upgrading = $paying->last();

    Payment::factory()->for($upgrading)->create([
        'status' => 'pending',
        'plan' => 'premium',
        'subscription_id' => $upgrading->subscription?->id,
    ]);

    Subscription::factory()->for(Business::factory()->create(['name' => 'Panadería La Esquina']))->create([
        'plan' => 'negocio',
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDays(2),
    ]);

    Subscription::factory()->for(Business::factory()->create(['name' => 'Estudio Contable Ríos']))->create([
        'plan' => 'negocio',
        'status' => SubscriptionStatus::Paused,
        'current_period_ends_at' => now()->subDays(9),
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the admin home shows figures that come from the rows below them', function (): void {
    $page = visit(route('admin.dashboard'))->resize(1280, 1100);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Comprobantes por verificar')
        ->assertSee('Cambios de plan por verificar')
        ->assertSee('Ingreso mensual recurrente')
        // The two businesses that did not pay, by name, in their own table.
        ->assertSee('Panadería La Esquina')
        ->assertSee('Estudio Contable Ríos')
        ->assertSee('En gracia')
        ->assertSee('Pausado')
        ->screenshot(filename: 'admin-home-desktop');

    expect(Payment::pendingReviewCount())->toBe(2)
        ->and(Payment::planChangesAwaitingReview())->toBe(1)
        ->and(Subscription::renewingWithin(7))->toHaveCount(3)
        ->and(Subscription::struggling())->toHaveCount(2);
});

/*
| Real time: a receipt that lands while she is looking shows up by itself,
| through the poll of the one island, and the tile counts it too.
*/
test('a receipt that arrives while the home is open appears without reloading', function (): void {
    $page = visit(route('admin.dashboard'))->resize(1280, 1100);

    $page->assertSee('Comprobantes esperando')->assertDontSee('Peluquería Recién Llegada');

    $late = Business::factory()->create(['name' => 'Peluquería Recién Llegada']);
    Payment::factory()->for($late)->create([
        'status' => 'pending',
        'plan' => 'negocio',
        'subscription_id' => $late->subscription?->id,
    ]);

    // The poll is 30 s; `assertSee` only retries ~5 s, so the page is given the
    // interval first.
    $page->wait(34)
        ->assertSee('Peluquería Recién Llegada')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-poll-arrived');

    expect(Payment::pendingReviewCount())->toBe(3);
});

/*
| The ranking is read from the stored figure and has to hold at every width:
| the page not overflowing says nothing about a table cut inside its card.
*/
test('the most-movement card reads the stored ranking and fits at every width', function (string $label, int $width, int $height, bool $dark): void {
    $busy = Business::factory()->create(['name' => 'Gimnasio Movimiento Total']);
    ConversationMessage::factory()->count(4)->create(['business_id' => $busy->id, 'created_at' => now()->subDay()]);
    $this->artisan('atendia:business-activity')->assertSuccessful();

    $page = visit(route('admin.dashboard'))->resize($width, $height);

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    $page->assertSee('Más movimiento')
        ->assertSee('Gimnasio Movimiento Total')
        ->assertSee('Calculado al')
        ->assertSee('Se actualiza solo')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-activity-'.$label.($dark ? '-dark' : '-light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0)
        ->and((int) $page->script(
            'Array.from(document.querySelectorAll(".pay-table-wrap"))
                .filter(w => w.offsetParent !== null)
                .map(w => w.scrollWidth - w.clientWidth)
                .reduce((a, b) => Math.max(a, b), 0)'
        ))->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 1100, false],
    'desktop dark' => ['desktop', 1280, 1100, true],
    'tablet light' => ['tablet', 900, 1100, false],
    'phone light' => ['phone', 390, 900, false],
    'phone dark' => ['phone', 390, 900, true],
]);

/*
| The pulse and the arrows are read at a glance, so they are looked at: the
| dot has to read as amber (late job) and each arrow beside its own name.
*/
test('the ranking shows each business arrow and the amber pulse of a late job', function (string $label, int $width, bool $dark): void {
    $climbs = Business::factory()->create(['name' => 'Gimnasio Sube']);
    $falls = Business::factory()->create(['name' => 'Peluquería Baja']);
    $arrives = Business::factory()->create(['name' => 'Taller Llegó Ahora']);

    ConversationMessage::factory()->count(9)->create(['business_id' => $falls->id, 'created_at' => now()->subDays(40)]);
    ConversationMessage::factory()->count(5)->create(['business_id' => $climbs->id, 'created_at' => now()->subDays(40)]);
    ConversationMessage::factory()->count(8)->create(['business_id' => $climbs->id, 'created_at' => now()->subDay()]);
    ConversationMessage::factory()->count(3)->create(['business_id' => $falls->id, 'created_at' => now()->subDay()]);
    ConversationMessage::factory()->count(1)->create(['business_id' => $arrives->id, 'created_at' => now()->subDay()]);

    $this->artisan('atendia:business-activity')->assertSuccessful();
    PlatformFigure::query()->update(['computed_at' => now()->subMinutes(100)]);

    $page = visit(route('admin.dashboard'))->resize($width, 1100);

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    $page->assertSee('Gimnasio Sube')
        ->assertSee('Taller Llegó Ahora')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-trend-'.$label);

    // The pulse is read in words, and its dot is big enough to be seen (it was 6px and nobody did).
    expect($page->script('document.querySelector("[data-testid=activity-beat]").dataset.beat'))->toBe('late')
        ->and($page->script('document.querySelector("[data-testid=activity-beat] .beat-word").textContent.trim()'))->toBe('Atrasado')
        ->and((int) $page->script('document.querySelector("[data-testid=activity-beat] .beat-dot").getBoundingClientRect().width'))->toBeGreaterThanOrEqual(12)
        ->and((int) $page->script('document.querySelectorAll("[data-testid^=activity-trend-]").length'))->toBe(3)
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop-light' => ['desktop-light', 1280, false],
    'phone-dark' => ['phone-dark', 390, true],
]);

/*
| Crediting without leaving: the row is resolved where it is, and the tile
| that counted it has to come down with it — a queue that empties while its
| number stays put is the screen lying about its own state.
*/
test('a receipt is credited from the home and the tile follows', function (): void {
    $payment = Payment::query()->where('status', 'pending')->oldest()->firstOrFail();

    $page = visit(route('admin.dashboard'))->resize(1280, 1100);

    $page->assertSee('Comprobantes esperando')
        ->click('[data-testid="home-approve-'.$payment->id.'"]')
        ->waitForText(__('billing.admin.approved'));

    expect($payment->fresh()->status->value)->toBe('paid')
        ->and(Payment::pendingReviewCount())->toBe(1);

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-after-approve');
});

/*
| The sixth figure she asked for on 2026-09-23, and the last to arrive: it
| needed somewhere to record a baja, which is the business ficha. Nothing is
| drawn until a baja exists, and then the tile carries the money walking out.
*/
test('a scheduled baja lights the home tile with its plan, amount and date', function (): void {
    $leaving = Business::factory()->create(['name' => 'Kiosco El Trébol']);

    $leaving->subscription->forceFill([
        'plan' => 'negocio',
        'status' => SubscriptionStatus::Active,
        'current_period_ends_at' => now()->addDays(12),
        'canceled_at' => now()->subDay(),
    ])->save();

    $page = visit(route('admin.dashboard'))->resize(1280, 1400);

    // The tile is always on screen; the row behind it lives in the "Bajas" tab.
    $page->assertSee(__('admin.home.tiles.leaving'))
        ->assertSee('Se deja de cobrar USD 79,00 por mes')
        ->click('Bajas')
        ->assertSee('Kiosco El Trébol')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-leaving');

    expect(Subscription::scheduledCancellations())->toHaveCount(1);
});

test('the home warns who owes the second step this week and the link lands filtered', function (): void {
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);

    $due = User::factory()->create(['name' => 'Rocío Paz', 'email' => 'rocio@atendia.test', 'email_verified_at' => now()]);
    $due->assignRole('support');
    $due->forceFill(['created_at' => now()->subDays(2)])->save();

    $protected = User::factory()->create(['name' => 'Marcos Gil', 'email' => 'marcos@atendia.test', 'email_verified_at' => now(), 'two_factor_whatsapp_at' => now()]);
    $protected->assignRole('support');

    $page = visit(route('admin.dashboard'))->resize(1280, 900);

    // The admin of the scenario is in the same plazo, so the notice counts two.
    $page->assertSee('Vencen esta semana los plazos del doble factor de 2 personas')
        ->assertSee('Rocío Paz')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-two-factor-due')
        ->click('Ver quién falta')
        ->assertPathIs('/admin/usuarios')
        // Landed already filtered: who has the step on is out of the list.
        ->assertSee('Rocío Paz')
        ->assertDontSee('Marcos Gil');
});

test('the admin home holds in the dark theme', function (): void {
    $page = visit(route('admin.dashboard'))->resize(1280, 1100);

    // A fresh context carries no localStorage, so dark needs the click.
    $page->click('@theme-toggle')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-dark');
});

/**
 * The two widths the design mandate names. 900px is the tightest the DESKTOP
 * layout gets: the sidebar still holds its 264px and the work area lives on
 * what is left, which is where a table gives way.
 */
test('the admin home holds at the two mandated widths', function (string $label, int $width, int $height): void {
    $page = visit(route('admin.dashboard'))->resize($width, $height);

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-home-'.$label);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBe(0);
})->with([
    'phone' => ['phone', 390, 1600],
    'tablet' => ['tablet', 900, 1200],
]);

/*
| The screen with nothing behind it. An admin desk is empty on a good day, so
| the emptiness has to read as good news and not as a broken panel.
*/
test('the admin home says the queues are empty instead of showing a void', function (): void {
    Payment::query()->delete();
    Subscription::query()->delete();

    $page = visit(route('admin.dashboard'))->resize(1280, 900);

    // The three subscription lists share one card behind tabs, so each empty
    // message is only on screen while its own tab is open.
    $page->assertNoJavaScriptErrors()
        ->assertSee('Nada por verificar')
        ->assertSee('Nada vence esta semana.')
        ->click('Vencidos')
        ->assertSee('Ningún negocio vencido ni pausado.')
        ->screenshot(filename: 'admin-home-empty');
});
