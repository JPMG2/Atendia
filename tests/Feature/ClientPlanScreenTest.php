<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function planClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

test('a guest is sent to login', function (): void {
    $this->get('/plan')->assertRedirect('/login');
});

test('the screen shows the trial plan, its price and the trial badge', function (): void {
    $this->actingAs(planClient());

    livewire('plan.index')
        ->assertSee(__('plan.title'))
        ->assertSee(__('plan.names.negocio'))
        ->assertSee('$79')
        ->assertSee(__('plan.trial_badge', ['days' => 14]));
});

test('the conversations meter counts this month active threads', function (): void {
    $user = planClient();
    $conversation = Conversation::factory()->create(['business_id' => $user->business_id]);
    ConversationMessage::factory()->for($conversation)->create(['business_id' => $user->business_id]);

    $this->actingAs($user);

    livewire('plan.index')
        ->assertSee(__('plan.meters.conversations_of', ['used' => 1, 'cap' => '1.000']));
});

test('higher rungs show their extras locked with the padlock hint, never hidden', function (): void {
    $user = planClient();
    // Expired trial: the business reads the ladder from the floor plan.
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user);

    livewire('plan.index')
        ->assertSee(__('plan.names.emprende'))
        ->assertSee(__('plan.locked_in', ['plan' => __('plan.names.negocio')]))
        ->assertSee(__('plan.locked_in', ['plan' => __('plan.names.premium')]))
        ->assertSee(__('plan.features.audio', ['cap' => 200]));
});

test('the ladder offers the landing annual toggle with two months free', function (): void {
    $this->actingAs(planClient());

    livewire('plan.index')
        ->assertSee(__('landing.pricing.billing_yearly'))
        ->assertSee(__('landing.pricing.billing_yearly_badge'))
        // Annual monthly equivalents mirror the landing exactly.
        ->assertSee('$24')
        ->assertSee('$66')
        ->assertSee('$124');
});

test('every other plan offers its change while the current one does not', function (): void {
    $this->actingAs(planClient());

    livewire('plan.index')
        ->assertSee(__('plan.yours'))
        ->assertSee(__('plan.change.choose', ['plan' => __('plan.names.premium')]))
        ->assertSee(__('plan.change.choose', ['plan' => __('plan.names.emprende')]))
        ->assertDontSee(__('plan.change.choose', ['plan' => __('plan.names.negocio')]));
});

/** A paying Negocio subscription, 10 days into a 30-day month. */
function paidPlanClient(): User
{
    $user = planClient();
    $user->business->subscription->forceFill([
        'plan' => 'negocio',
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => null,
        'current_period_ends_at' => now()->addDays(20),
    ])->save();

    return $user;
}

test('moving up charges only the difference for the days left of the period', function (): void {
    $subscription = paidPlanClient()->business->subscription->fresh();
    $days = $subscription->daysUntilPayment();
    $expected = round((149 - 79) * $days / $subscription->periodLengthDays(), 2);

    expect($subscription->upgradeCharge(Plan::named('premium')))->toBe($expected)
        ->and($subscription->upgradeCharge(Plan::named('emprende')))->toBe(0.0);
});

test('a yearly plan moving up pays the yearly difference for the days left', function (): void {
    $subscription = paidPlanClient()->business->subscription;
    $subscription->forceFill(['billing_cycle' => 'yearly', 'current_period_ends_at' => now()->addDays(100)])->save();
    $subscription = $subscription->fresh();

    $expected = round((1490 - 790) * $subscription->daysUntilPayment() / $subscription->periodLengthDays(), 2);

    expect($subscription->upgradeCharge(Plan::named('premium')))->toBe($expected);
});

test('the trial moves nothing today: a choice waits for the trial end', function (): void {
    $subscription = planClient()->business->subscription;

    expect($subscription->upgradeCharge(Plan::named('premium')))->toBe(0.0);
});

test('moving down is scheduled for the period end and can be cancelled', function (): void {
    $this->actingAs(paidPlanClient());

    livewire('plan.index')
        ->assertSee(__('plan.change.down', ['plan' => __('plan.names.emprende')]))
        ->call('changePlan', 'emprende')
        ->assertSet('scheduledPlan', 'emprende')
        ->assertSee(__('plan.change.cancel'))
        ->call('cancelScheduledChange')
        ->assertSet('scheduledPlan', null);
});

test('moving up applies today and asks for the receipt of the difference', function (): void {
    $this->actingAs(paidPlanClient());

    livewire('plan.index')
        ->assertSee(__('plan.change.up', ['plan' => __('plan.names.premium')]))
        ->call('changePlan', 'premium')
        ->assertSet('upgradedPlan', 'premium')
        ->assertSee(__('plan.change.down', ['plan' => __('plan.names.negocio')]))
        ->assertSee(__('plan.change.upload'))
        ->assertSee(route('my-payments'));
});

test('an unknown or current plan changes nothing', function (): void {
    $this->actingAs(paidPlanClient());

    livewire('plan.index')
        ->call('changePlan', 'negocio')
        ->call('changePlan', 'platinum')
        ->assertSet('scheduledPlan', null)
        ->assertSet('upgradedPlan', null);
});
