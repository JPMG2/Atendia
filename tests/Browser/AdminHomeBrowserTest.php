<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Payment;
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
