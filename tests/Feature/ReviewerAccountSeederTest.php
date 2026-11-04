<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\ReviewerAccountSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Country::factory()->create(['iso2' => 'VE']);
});

test('the reviewer gets a verified client with an active Negocio plan and sample data', function (): void {
    $this->seed(ReviewerAccountSeeder::class);

    $user = User::query()->where('email', ReviewerAccountSeeder::EMAIL)->firstOrFail();

    expect($user->hasRole('client'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull();

    app(Tenant::class)->for($user->business_id, function (): void {
        $subscription = Subscription::query()->firstOrFail();

        expect($subscription->plan)->toBe('negocio')
            ->and($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->current_period_ends_at->isAfter(now()->addYears(4)))->toBeTrue()
            ->and(Conversation::query()->count())->toBe(3)
            ->and(Appointment::query()->count())->toBe(2);
    });
});

test('the reviewer business never counts as a client of the platform', function (): void {
    $this->seed(ReviewerAccountSeeder::class);

    expect(Business::query()->count())->toBe(0)
        ->and(Business::query()->where('is_demo', true)->count())->toBe(1);
});

test('running the seeder twice keeps one account and the first password', function (): void {
    $this->seed(ReviewerAccountSeeder::class);
    $hash = User::query()->where('email', ReviewerAccountSeeder::EMAIL)->value('password');

    $this->seed(ReviewerAccountSeeder::class);

    expect(User::query()->where('email', ReviewerAccountSeeder::EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', ReviewerAccountSeeder::EMAIL)->value('password'))->toBe($hash)
        ->and(Business::query()->where('is_demo', true)->count())->toBe(1);
});

test('the reviewer signs in over the API from any device with no code, while a real client still meets the gate', function (): void {
    $this->seed(ReviewerAccountSeeder::class);
    $password = 'Reviewer-pass-123';
    User::query()->where('email', ReviewerAccountSeeder::EMAIL)->firstOrFail()->forceFill(['password' => bcrypt($password)])->save();
    $login = fn (string $email, string $device) => $this->withHeaders(['X-Device-Id' => $device, 'X-Device-Name' => 'Pixel'])
        ->postJson('/api/v1/login', ['email' => $email, 'password' => $password]);

    $login(ReviewerAccountSeeder::EMAIL, 'device-one-0000000000')->assertOk()->assertJsonStructure(['token']);
    $login(ReviewerAccountSeeder::EMAIL, 'device-two-0000000000')->assertOk()->assertJsonStructure(['token']);

    payingUser(['email' => 'owner@example.com', 'password' => bcrypt($password)]);

    $login('owner@example.com', 'device-one-0000000000')->assertOk();
    $login('owner@example.com', 'device-two-0000000000')->assertStatus(202)->assertJsonStructure(['challenge']);
});

test('the reviewer can open the client dashboard and is kept out of the admin', function (): void {
    $this->seed(ReviewerAccountSeeder::class);
    $user = User::query()->where('email', ReviewerAccountSeeder::EMAIL)->firstOrFail();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get('/admin')->assertForbidden();
});
