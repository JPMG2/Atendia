<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The admin list of businesses — filtered and paged by the database
|--------------------------------------------------------------------------
| The state of a business is derived (suspension, cancellation, period end),
| so the SQL filter and the PHP `situation()` are two writings of one
| decision. These tests make them agree on every state.
*/

/** @param array<string, mixed> $subscription Overrides applied to the plan row. */
function directoryBusiness(string $name, ?SubscriptionStatus $status = null, array $subscription = [], bool $suspended = false): Business
{
    $business = Business::factory()->create(['name' => $name]);

    if ($suspended) {
        $business->forceFill(['suspended_at' => now()])->save();
    }

    if ($status !== null) {
        Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => $status,
            'current_period_ends_at' => now()->addDays(10),
        ])->forceFill($subscription)->save();
    }

    return $business->refresh();
}

/** One business per situation the list can name, plus the ones that overlap. */
function directoryFixture(): void
{
    directoryBusiness('Activo', SubscriptionStatus::Active);
    directoryBusiness('Prueba', SubscriptionStatus::Trialing);
    directoryBusiness('En gracia', SubscriptionStatus::PastDue);
    directoryBusiness('Pausado', SubscriptionStatus::Paused);
    // A new business is born with its trial row, so "no plan" has to be made.
    Subscription::query()->where('business_id', directoryBusiness('Sin plan')->id)->delete();
    directoryBusiness('Suspendido', SubscriptionStatus::Active, suspended: true);
    directoryBusiness('Se va', SubscriptionStatus::Active, ['canceled_at' => now()->subDay()]);
    directoryBusiness('Ya se fue', SubscriptionStatus::Active, [
        'canceled_at' => now()->subDays(40),
        'current_period_ends_at' => now()->subDays(5),
    ]);
    // Behind on payment but already leaving: it is "leaving", not a collection case.
    directoryBusiness('Debe y se va', SubscriptionStatus::PastDue, ['canceled_at' => now()->subDay()]);
}

test('the database filter returns exactly the businesses whose situation says so', function (string $situation): void {
    directoryFixture();

    $expected = Business::query()->with('subscription')->get()
        ->filter(fn (Business $business): bool => in_array(
            $business->situation(),
            $situation === 'problem' ? ['past_due', 'paused'] : [$situation],
            true,
        ))
        ->pluck('name')->sort()->values()->all();

    expect($expected)->not->toBeEmpty()
        ->and(Business::directory('', $situation)->pluck('name')->sort()->values()->all())->toBe($expected);
})->with(['suspended', 'none', 'ended', 'canceling', 'active', 'trialing', 'past_due', 'paused', 'problem']);

test('a suspended business is only suspended, whatever its plan row says', function (): void {
    directoryFixture();

    expect(Business::directory('', 'active')->pluck('name')->all())->not->toContain('Suspendido')
        ->and(Business::directory('', 'suspended')->pluck('name')->all())->toBe(['Suspendido']);
});

test('the list stops at the limit while the count still says how many match', function (): void {
    foreach (range(1, 7) as $number) {
        directoryBusiness('Negocio '.$number, SubscriptionStatus::Active);
    }
    directoryBusiness('Otro rubro', SubscriptionStatus::PastDue);

    expect(Business::directory('', '', 3))->toHaveCount(3)
        ->and(Business::directory('', '', 3)->first()->name)->toBe('Negocio 1')
        ->and(Business::directoryMatching())->toBe(8)
        ->and(Business::directoryMatching('Negocio'))->toBe(7)
        ->and(Business::directoryMatching('Negocio', 'past_due'))->toBe(0)
        ->and(Business::directoryMatching('', 'past_due'))->toBe(1);
});
