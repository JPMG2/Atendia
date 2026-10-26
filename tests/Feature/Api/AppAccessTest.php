<?php

declare(strict_types=1);

use App\Enums\AppAccess;
use App\Enums\SubscriptionStatus;
use App\Mail\DeviceChallengeCode;
use App\Models\Business;
use App\Models\LoginDevice;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;

uses(DatabaseTransactions::class);

/** A business at the given point of its billing life, for an owner who signs in from the app. */
function appOwnerAt(string $stage): User
{
    $business = Business::factory()->create();
    $subscription = $business->subscription;

    match ($stage) {
        'trial' => null,
        'trial_over' => $subscription->forceFill(['trial_ends_at' => now()->subDay()])->save(),
        'grace_never_paid' => $subscription->forceFill([
            'trial_ends_at' => now()->subDays(2),
            'current_period_ends_at' => now()->subDay(),
            'status' => SubscriptionStatus::PastDue,
        ])->save(),
        'paused_never_paid' => $subscription->forceFill([
            'trial_ends_at' => now()->subDays(9),
            'current_period_ends_at' => now()->subDays(8),
            'status' => SubscriptionStatus::Paused,
        ])->save(),
        'paid' => (function () use ($business, $subscription): void {
            Payment::factory()->paid()->create(['business_id' => $business->id, 'subscription_id' => $subscription->id]);
            $subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->addDays(10)])->save();
        })(),
        'grace_after_paying' => (function () use ($business, $subscription): void {
            Payment::factory()->paid()->create(['business_id' => $business->id, 'subscription_id' => $subscription->id]);
            $subscription->forceFill(['status' => SubscriptionStatus::PastDue, 'current_period_ends_at' => now()->subDay()])->save();
        })(),
        'paused_after_paying' => (function () use ($business, $subscription): void {
            Payment::factory()->paid()->create(['business_id' => $business->id, 'subscription_id' => $subscription->id]);
            $subscription->forceFill(['status' => SubscriptionStatus::Paused, 'current_period_ends_at' => now()->subDays(9)])->save();
        })(),
        'canceling' => $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->addDays(10),
            'canceled_at' => now(),
        ])->save(),
        'ended' => $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->subDay(),
            'canceled_at' => now()->subDays(5),
        ])->save(),
        'suspended' => $business->forceFill(['suspended_at' => now()])->save(),
    };

    return User::factory()->create(['business_id' => $business->id]);
}

function signInFromTheApp(User $user)
{
    return test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ]);
}

it('opens the app only to a business that has paid, and says why otherwise', function (string $stage, AppAccess $expected): void {
    expect(appOwnerAt($stage)->appAccess())->toBe($expected);
})->with([
    'still on the trial' => ['trial', AppAccess::OnTrial],
    'trial over, nothing paid' => ['trial_over', AppAccess::TrialEnded],
    'grace days after a trial nobody paid' => ['grace_never_paid', AppAccess::TrialEnded],
    'paused without ever paying' => ['paused_never_paid', AppAccess::TrialEnded],
    'paid and current' => ['paid', AppAccess::Allowed],
    'late but paid before' => ['grace_after_paying', AppAccess::Allowed],
    'paused after paying' => ['paused_after_paying', AppAccess::Paused],
    'leaving but served to the period end' => ['canceling', AppAccess::Allowed],
    'cancellation ran its course' => ['ended', AppAccess::Ended],
    'suspended by moderation' => ['suspended', AppAccess::Suspended],
]);

it('refuses an account without a business', function (): void {
    expect(User::factory()->create()->appAccess())->toBe(AppAccess::NoBusiness);
});

it('gives the admin its own door, a code and never a token, and still refuses a person with no business and no role', function (): void {
    Mail::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // No plan to check for the platform's people: they answer the second step instead.
    signInFromTheApp($admin)->assertStatus(202)->assertJsonMissingPath('token');

    signInFromTheApp(User::factory()->create())->assertForbidden()->assertJsonPath('reason', 'no_business');
});

it('sends no code and records no device to someone the plan keeps out', function (): void {
    Mail::fake();
    $user = appOwnerAt('trial');

    signInFromTheApp($user)
        ->assertForbidden()
        ->assertJsonPath('code', 'app_access_denied')
        ->assertJsonPath('reason', 'on_trial');

    Mail::assertNothingQueued();
    expect($user->loginDevices()->count())->toBe(0)->and($user->tokens()->count())->toBe(0);
});

it('speaks to the person in their own region', function (): void {
    $user = appOwnerAt('trial_over');

    $neutral = signInFromTheApp($user)->json('message');
    $voseo = test()->withHeaders([...apiDeviceHeaders(), 'Accept-Language' => 'es-AR'])->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('message');

    expect($neutral)->toContain('Contrata')->and($voseo)->toContain('Contratá');
});

it('lets a paying owner in and asks the new install for its code first', function (): void {
    Mail::fake();
    $user = appOwnerAt('paid');
    LoginDevice::factory()->for($user)->create();

    signInFromTheApp($user)->assertStatus(202)->assertJsonStructure(['challenge' => ['id']]);

    Mail::assertQueued(DeviceChallengeCode::class);
});

it('closes the app on the very next call when the plan lapses', function (): void {
    $user = appOwnerAt('paid');
    $token = appInstallToken($user);
    $headers = apiDeviceHeaders();

    $this->withToken($token)->withHeaders($headers)->getJson('/api/v1/me')->assertOk();

    $user->business->subscription->forceFill(['status' => SubscriptionStatus::Paused])->save();
    app('auth')->forgetGuards();

    $this->withToken($token)->withHeaders($headers)->getJson('/api/v1/me')
        ->assertForbidden()->assertJsonPath('reason', 'paused');
});

it('lets a lapsed account sign out so its token is never trapped', function (): void {
    $user = appOwnerAt('paused_after_paying');
    $token = appInstallToken($user);

    $this->withToken($token)->withHeaders(apiDeviceHeaders())->postJson('/api/v1/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
