<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);
});

/** An admin who may open the collection queue, with the menu the layout reads. */
function collectionsAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    test()->seed(MenuSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

function owing(string $name, string $plan, SubscriptionStatus $status, int $daysAgo = 10, ?string $email = null): Subscription
{
    $business = Business::factory()->create(['name' => $name, 'billing_email' => $email ?? fake()->safeEmail()]);

    return Subscription::factory()->create([
        'business_id' => $business->id,
        'plan' => $plan,
        'billing_cycle' => 'monthly',
        'status' => $status,
        'current_period_ends_at' => now()->subDays($daysAgo),
    ]);
}

test('the queue is sorted by what is at stake, not by who owed longest', function (): void {
    // The oldest debt is the smallest: sorted by date it would be on top, and
    // the call worth making today is the other one.
    owing('Kiosco La Esquina', 'emprende', SubscriptionStatus::PastDue, daysAgo: 60);
    owing('Centro Odontológico', 'premium', SubscriptionStatus::Paused, daysAgo: 3);

    $queue = Subscription::collectionQueue();

    expect($queue->first()->business->name)->toBe('Centro Odontológico')
        ->and($queue->last()->business->name)->toBe('Kiosco La Esquina');
});

test('the money at risk is the sum of what the owing ones are worth a month', function (): void {
    owing('Uno', 'negocio', SubscriptionStatus::PastDue);
    owing('Dos', 'negocio', SubscriptionStatus::Paused);
    // Paying on time is not at risk.
    owing('Tres', 'negocio', SubscriptionStatus::Active, daysAgo: -20);

    $monthly = Subscription::where('plan', 'negocio')->first()->monthlyValue();

    expect(Subscription::amountAtRisk())->toBe($monthly * 2);
});

test('a demo business of the landing never counts as money', function (): void {
    // Verified on the live database the 2026-10-07: 632 of the 790 the Inicio
    // showed as MRR were the eight seeded demos. Inventing revenue is worse
    // than showing none.
    $subscription = owing('Clínica Vida', 'negocio', SubscriptionStatus::PastDue);
    $subscription->business->update(['is_demo' => true]);

    expect(Subscription::amountAtRisk())->toBe(0.0)
        ->and(Subscription::monthlyRecurringRevenue())->toBe(0.0)
        ->and(Subscription::payingCount())->toBe(0)
        ->and(Subscription::collectionQueue())->toBeEmpty();
});

test('a demo with any other address is still out, which the email could not catch', function (): void {
    // The hole the flag closes: deduced from the seeded address, a demo added
    // tomorrow with a different one walked straight into the revenue.
    $subscription = owing('Demo nuevo', 'premium', SubscriptionStatus::PastDue, email: 'otro-correo@atendia.app');
    $subscription->business->update(['is_demo' => true]);

    expect(Subscription::amountAtRisk())->toBe(0.0)
        ->and(Business::servedCount())->toBe(0);
});

test('what renews this week travels with what is already owed, because the call is the same', function (): void {
    owing('Debe', 'negocio', SubscriptionStatus::PastDue);
    owing('Vence', 'negocio', SubscriptionStatus::Active, daysAgo: -3);

    expect(Subscription::collectionQueue())->toHaveCount(2);
});

test('the screen names the amount at risk and the state of each row', function (): void {
    owing('Centro Odontológico', 'premium', SubscriptionStatus::Paused, daysAgo: 4);

    $this->actingAs(collectionsAdmin())->get(route('admin.collections'))
        ->assertOk()
        ->assertSee('Centro Odontológico')
        ->assertSee(__('collections.states.paused'))
        ->assertSee(__('collections.risk.at_risk'))
        ->assertSee(trans_choice('collections.overdue', 4, ['count' => 4]));
});

test('with nobody owing the screen says so instead of drawing an empty table', function (): void {
    $this->actingAs(collectionsAdmin())->get(route('admin.collections'))
        ->assertOk()
        ->assertSee(__('collections.empty_title'))
        // A heading that exists only inside the table: "Negocio" also names a
        // menu group, so asserting on it would always find the sidebar.
        ->assertDontSee(__('collections.table.monthly'));
});

test('a client cannot reach the collection queue', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->create())->get(route('admin.collections'))->assertForbidden();
});
