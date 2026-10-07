<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\RevenueSnapshot;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);
});

function paying(string $plan = 'negocio'): Subscription
{
    return Subscription::factory()->create([
        'business_id' => Business::factory()->create()->id,
        'plan' => $plan,
        'billing_cycle' => 'monthly',
        'status' => SubscriptionStatus::Active,
        'current_period_ends_at' => now()->addDays(20),
    ]);
}

test('the photo keeps the revenue of the month and what sustains it', function (): void {
    paying();
    paying('premium');

    $snapshot = RevenueSnapshot::capture();

    expect((float) $snapshot->mrr)->toBe(Subscription::monthlyRecurringRevenue())
        ->and($snapshot->paying)->toBe(2)
        ->and($snapshot->month->day)->toBe(1);
});

test('taking it twice in a month corrects the row instead of adding another', function (): void {
    paying();
    RevenueSnapshot::capture();

    paying('premium');
    $second = RevenueSnapshot::capture();

    expect(RevenueSnapshot::count())->toBe(1)
        ->and((float) $second->mrr)->toBe(Subscription::monthlyRecurringRevenue());
});

test('what moved is measured against the previous photo, not against zero', function (): void {
    $past = CarbonImmutable::now()->subMonth();
    paying();
    RevenueSnapshot::capture($past);

    // A second business joins this month.
    paying('premium');
    $now = RevenueSnapshot::capture();

    $premium = Subscription::where('plan', 'premium')->first()->monthlyValue();

    expect((float) $now->gained)->toBe($premium)
        ->and((float) $now->lost)->toBe(0.0);
});

test('revenue going down is recorded as lost, not as a negative gain', function (): void {
    $leaving = paying('premium');
    RevenueSnapshot::capture(CarbonImmutable::now()->subMonth());

    $value = $leaving->monthlyValue();
    $leaving->delete();

    expect((float) RevenueSnapshot::capture()->lost)->toBe($value)
        ->and((float) RevenueSnapshot::capture()->gained)->toBe(0.0);
});

test('the first month has nothing to be compared with, and says null instead of zero', function (): void {
    paying();
    RevenueSnapshot::capture();

    // Comparing a first month against a month nobody photographed would print
    // growth out of thin air.
    expect(RevenueSnapshot::before(CarbonImmutable::now()))->toBeNull();
});

test('a demo business never reaches the photo', function (): void {
    $demo = paying();
    $demo->business->update(['is_demo' => true]);

    $snapshot = RevenueSnapshot::capture();

    expect((float) $snapshot->mrr)->toBe(0.0)
        ->and($snapshot->paying)->toBe(0);
});

test('the command stores the month and says what it stored', function (): void {
    paying();

    $this->artisan('atendia:revenue-snapshot')->assertSuccessful();

    expect(RevenueSnapshot::count())->toBe(1);
});
