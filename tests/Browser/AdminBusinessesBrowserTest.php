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

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    // A business is born with its trial subscription (Business boot), so the
    // plan row is UPDATED here. Creating a second one would leave the screen
    // reading the latest while the test edited the first.
    $this->paying = Business::factory()->create(['name' => 'Panadería La Esquina']);

    $this->paying->subscription->forceFill([
        'plan' => 'negocio',
        'status' => SubscriptionStatus::Active,
        'billing_cycle' => 'monthly',
        'current_period_ends_at' => now()->addDays(20),
    ])->save();

    Payment::factory()->count(2)->for($this->paying)->create(['status' => 'paid', 'paid_at' => now()->subDays(5)]);

    $behind = Business::factory()->create(['name' => 'Estudio Contable Ríos']);

    $behind->subscription->forceFill([
        'plan' => 'negocio',
        'status' => SubscriptionStatus::Paused,
        'current_period_ends_at' => now()->subDays(9),
    ])->save();

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the directory shows every business with its plan, state and debt', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 1100);

    $page->assertSee('Panadería La Esquina')
        ->assertSee('Estudio Contable Ríos')
        ->assertSee('Al día')
        ->assertSee('Pausado')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-businesses-list');
});

test('the ficha opens with the money history of that business', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 1400);

    $page->click('[data-testid="biz-open-'.$this->paying->id.'"]')
        ->waitForText(__('admin.businesses.card.payments'))
        ->assertSee(__('admin.businesses.cancel.title'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-businesses-ficha');
});

/*
| The decision she made on 2026-10-03: a business that already paid keeps being
| served until the date it actually leaves. The row records WHEN it asked; the
| state is derived from that date, so nothing has to run at midnight.
*/
test('a baja is recorded and the business keeps being served until its period ends', function (): void {
    $page = visit(route('admin.businesses'))->resize(1280, 1400);

    $page->click('[data-testid="biz-open-'.$this->paying->id.'"]')
        ->waitForText(__('admin.businesses.cancel.action'))
        ->click('[data-testid="biz-cancel-'.$this->paying->id.'"]')
        // By selector, not by text: the dialog's own button reads the same as
        // the one that opened it ("Registrar la baja").
        ->click('.dialog-foot [x-ref="accept"]')
        ->waitForText(__('admin.businesses.cancel.done'));

    $subscription = $this->paying->subscription->refresh();

    expect($subscription->canceled_at)->not->toBeNull()
        ->and($subscription->isCanceling())->toBeTrue()
        ->and($subscription->hasEnded())->toBeFalse()
        ->and(Subscription::scheduledCancellations())->toHaveCount(1);

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-businesses-canceled');
});

test('a baja whose period already ran out is over, with no grace and no pause', function (): void {
    $subscription = $this->paying->subscription;

    $subscription->forceFill([
        'canceled_at' => now()->subMonth(),
        'current_period_ends_at' => now()->subDay(),
    ])->save();

    expect($subscription->refresh()->hasEnded())->toBeTrue()
        ->and($subscription->isCanceling())->toBeFalse()
        ->and(Subscription::scheduledCancellations())->toHaveCount(0);

    visit(route('admin.businesses'))->resize(1280, 1100)
        ->assertSee(__('admin.businesses.states.ended'))
        ->assertNoJavaScriptErrors();
});

test('the directory holds at the two mandated widths', function (string $label, int $width, int $height): void {
    $page = visit(route('admin.businesses'))->resize($width, $height);

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-businesses-'.$label);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBe(0);
})->with([
    'phone' => ['phone', 390, 1600],
    'tablet' => ['tablet', 900, 1200],
]);
