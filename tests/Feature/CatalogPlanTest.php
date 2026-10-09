<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Models\Business;
use App\Models\CatalogForm;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Plans master
|--------------------------------------------------------------------------
| The figures every card and gate reads, edited from the catalog hub. The
| rows are seeded by TestCase; here we edit what is already there.
*/

beforeEach(function (): void {
    app()->setLocale('es');
});

/** @return array<string, mixed> What the form would send for a plan, with some figures changed. */
function planFormWith(string $code, array $changes = []): array
{
    $plan = SubscriptionPlan::query()->where('code', $code)->firstOrFail();

    return [...$plan->only([
        'price', 'conversations_per_month', 'team_seats', 'messages_per_hour', 'audio_minutes_per_month', 'statistics',
        'ask_per_month', 'catalog_photos', 'photos_per_item', 'ai_alert_share', 'trial_days', 'reads_media', 'departments',
        'daily_digest', 'is_featured',
    ]), ...$changes];
}

function editPlan(string $code, array $changes = [])
{
    $plan = SubscriptionPlan::query()->where('code', $code)->firstOrFail();
    $component = Livewire::test('catalog.plan')->call('openEdit', $plan->id);

    foreach (planFormWith($code, $changes) as $field => $value) {
        $component->set('form.data.'.$field, $value);
    }

    return $component;
}

test('the master is a row of the hub, behind its own permission', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin);

    expect(CatalogForm::visibleTo($admin)->pluck('component')->all())->toContain('catalog.plan');

    $support = User::factory()->create(['email_verified_at' => now()]);
    $support->assignRole('support');

    expect(CatalogForm::visibleTo($support)->pluck('component')->all())->not->toContain('catalog.plan');
});

test('the list shows each plan with the businesses really on it', function (): void {
    // A new business starts on its own trial plan; put these two on Premium so the count is theirs alone.
    $businesses = Business::factory()->count(2)->create();
    Subscription::query()->whereIn('business_id', $businesses->modelKeys())->update(['plan' => 'premium']);

    $rows = (new SubscriptionPlan)->catalogRows()->keyBy('code');

    expect($rows->keys()->all())->toBe(['emprende', 'negocio', 'premium'])
        ->and($rows['premium']['businesses'])->toBe(2)
        ->and($rows['negocio']['businesses'])->toBe(0)
        ->and($rows['negocio']['featured'])->toBeTrue()
        ->and($rows['negocio']['trial'])->toBe(14);
});

test('editing a plan changes what the whole product reads, at once', function (): void {
    expect(Plan::named('negocio')->price)->toBe(79);

    editPlan('negocio', ['price' => 85, 'conversations_per_month' => 1200])
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    // The catalog is cached whole: saving has to drop it, or the landing keeps the old price.
    expect(Plan::named('negocio')->price)->toBe(85)
        ->and(Plan::named('negocio')->conversationsPerMonth)->toBe(1200);
});

test('there is no way to create a plan from here', function (): void {
    $before = SubscriptionPlan::query()->count();

    Livewire::test('catalog.plan')->call('create')->assertReturned(false);

    expect(SubscriptionPlan::query()->count())->toBe($before);
});

test('a plan may not give less than the one below it, nor more than the one above', function (): void {
    // Emprende (300) above Negocio (1000) would turn the ladder upside down.
    editPlan('emprende', ['conversations_per_month' => 1500])
        ->call('update')
        ->assertHasErrors(['conversations_per_month']);

    // Premium (3000) under Negocio (1000) the other way round.
    editPlan('premium', ['conversations_per_month' => 900])
        ->call('update')
        ->assertHasErrors(['conversations_per_month']);

    expect(Plan::named('premium')->conversationsPerMonth)->toBe(3000);
});

test('a feature sold in a lower plan cannot be missing from a higher one', function (): void {
    editPlan('premium', ['departments' => false])
        ->call('update')
        ->assertHasErrors(['departments']);

    // And the other way: Negocio keeping what the plan above it has dropped.
    SubscriptionPlan::query()->where('code', 'premium')->update(['reads_media' => false]);

    editPlan('negocio', ['reads_media' => true])
        ->call('update')
        ->assertHasErrors(['reads_media']);
});

test('"Más elegido" and the free trial live on one plan at a time', function (): void {
    editPlan('premium', ['is_featured' => true, 'trial_days' => 7])
        ->call('update')
        ->assertHasNoErrors();

    $plans = SubscriptionPlan::query()->get()->keyBy('code');

    expect($plans['premium']->is_featured)->toBeTrue()
        ->and($plans['negocio']->is_featured)->toBeFalse()
        ->and($plans['premium']->trial_days)->toBe(7)
        ->and($plans['negocio']->trial_days)->toBeNull()
        ->and(Plan::trial()->code)->toBe('premium');
});

test('a blank trial means no trial, not a trial of zero days', function (): void {
    editPlan('negocio', ['trial_days' => ''])->call('update')->assertHasNoErrors();

    expect(SubscriptionPlan::query()->where('code', 'negocio')->value('trial_days'))->toBeNull();
});

test('figures out of range are refused', function (): void {
    editPlan('negocio', ['price' => 0])->call('update')->assertHasErrors(['price']);
    editPlan('negocio', ['statistics' => 'everything'])->call('update')->assertHasErrors(['statistics']);
    editPlan('negocio', ['ai_alert_share' => 150])->call('update')->assertHasErrors(['ai_alert_share']);
});

test('a seed never puts back what was edited in the master', function (): void {
    editPlan('negocio', ['price' => 85])->call('update')->assertHasNoErrors();

    $this->seed(PlanSeeder::class);

    expect(Plan::named('negocio')->price)->toBe(85);
});

test('who changed a price, and from what, stays in the audit trail', function (): void {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($admin);

    editPlan('negocio', ['price' => 85])->call('update')->assertHasNoErrors();

    $entry = Activity::query()->where('subject_type', SubscriptionPlan::class)->latest('id')->firstOrFail();

    expect($entry->causer_id)->toBe($admin->id)
        // In v5 the changes live in `attribute_changes`, not in `properties`.
        ->and($entry->attribute_changes['attributes']['price'])->toBe(85)
        ->and($entry->attribute_changes['old']['price'])->toBe(79);
});
