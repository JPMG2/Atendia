<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an unknown or missing plan code falls to the floor plan, never upward', function (): void {
    expect(Plan::named(null)->code)->toBe('emprende')
        ->and(Plan::named('typo-premium')->code)->toBe('emprende')
        ->and(Plan::named('premium')->code)->toBe('premium');
});

test('a new business is born on the reverse trial of the trial plan', function (): void {
    $business = Business::factory()->create();

    expect($business->subscription)->not->toBeNull()
        ->and($business->subscription->plan)->toBe(config('atendia.trial.plan'))
        ->and($business->subscription->onTrial())->toBeTrue()
        ->and($business->plan()->code)->toBe(config('atendia.trial.plan'));
});

test('an expired trial falls to the floor plan without any downgrade job', function (): void {
    $business = Business::factory()->create();
    $business->subscription->update(['trial_ends_at' => now()->subDay()]);

    expect($business->fresh()->plan()->code)->toBe('emprende');
});

test('a paid subscription with no trial date rules as written', function (): void {
    $business = Business::factory()->create();
    Subscription::factory()->create([
        'business_id' => $business->id,
        'plan' => 'premium',
        'trial_ends_at' => null,
    ]);

    expect($business->fresh()->plan()->code)->toBe('premium');
});

test('the ladder follows config order and knows who sits below whom', function (): void {
    $ladder = Plan::ladder();

    expect(array_map(fn (Plan $plan): string => $plan->code, $ladder))->toBe(['emprende', 'negocio', 'premium'])
        ->and($ladder[0]->isBelow($ladder[2]))->toBeTrue()
        ->and($ladder[2]->isBelow($ladder[0]))->toBeFalse()
        ->and($ladder[0]->allowsAudio)->toBeFalse()
        ->and($ladder[1]->allowsAudio)->toBeTrue();
});

test('trial days left never reads zero while the trial still runs', function (): void {
    $business = Business::factory()->create();
    $business->subscription->update(['trial_ends_at' => now()->addHours(3)]);

    expect($business->fresh()->subscription->trialDaysLeft())->toBe(1);
});
