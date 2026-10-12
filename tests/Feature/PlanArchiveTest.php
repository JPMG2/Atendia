<?php

declare(strict_types=1);

use App\Actions\Catalog\ArchivePlan;
use App\Actions\Catalog\RestorePlan;
use App\Classes\Main\Plan;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Archiving a plan
|--------------------------------------------------------------------------
| A plan comes off sale without anybody losing it: whoever holds it keeps its
| price and its caps, and only what is OFFERED changes. The two refusals are
| the ones that would leave the product with nothing to sell or to start on.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(PlanSeeder::class);
});

function planId(string $code): int
{
    return SubscriptionPlan::query()->where('code', $code)->value('id');
}

test('an archived plan is not offered, but it is still found by its code', function (): void {
    expect(app(ArchivePlan::class)->handle(planId('premium')))->toBe(ArchivePlan::ARCHIVED);

    expect(collect(Plan::ladder())->pluck('code')->all())->toBe(['emprende', 'negocio'])
        ->and(collect(Plan::everyPlan())->pluck('code')->all())->toBe(['emprende', 'negocio', 'premium']);

    // Whoever holds it keeps its caps: it resolves, whole, by its own code.
    $held = Plan::named('premium');

    expect($held->isArchived)->toBeTrue()
        ->and($held->code)->toBe('premium')
        ->and($held->conversationsPerMonth)->toBe(3000);
});

test('a business on an archived plan still sees it on its own ladder', function (): void {
    app(ArchivePlan::class)->handle(planId('premium'));

    $codes = collect(Plan::ladderFor(Plan::named('premium')))->pluck('code')->all();
    $others = collect(Plan::ladderFor(Plan::named('negocio')))->pluck('code')->all();

    expect($codes)->toBe(['emprende', 'negocio', 'premium'])
        ->and($others)->toBe(['emprende', 'negocio']);
});

test('the floor a typo falls to is the cheapest plan still sold', function (): void {
    app(ArchivePlan::class)->handle(planId('emprende'));

    expect(Plan::named('typo')->code)->toBe('negocio')
        ->and(Plan::named(null)->code)->toBe('negocio');
});

test('the plan that carries the free trial cannot be archived', function (): void {
    expect(app(ArchivePlan::class)->handle(planId('negocio')))->toBe(ArchivePlan::HAS_TRIAL)
        ->and(SubscriptionPlan::query()->where('code', 'negocio')->value('archived_at'))->toBeNull();
});

test('the last plan on sale cannot be archived', function (): void {
    app(ArchivePlan::class)->handle(planId('emprende'));
    app(ArchivePlan::class)->handle(planId('premium'));

    // Only "negocio" is left, and it carries the trial as well: the trial answers first.
    SubscriptionPlan::query()->where('code', 'negocio')->update(['trial_days' => null]);

    expect(app(ArchivePlan::class)->handle(planId('negocio')))->toBe(ArchivePlan::LAST_SOLD)
        ->and(collect(Plan::ladder())->pluck('code')->all())->toBe(['negocio']);
});

test('archiving takes the "most chosen" badge off a card nobody can see', function (): void {
    SubscriptionPlan::query()->where('code', 'premium')->update(['is_featured' => true]);

    app(ArchivePlan::class)->handle(planId('premium'));

    expect((bool) SubscriptionPlan::query()->where('code', 'premium')->value('is_featured'))->toBeFalse();
});

test('restoring puts the plan back on sale, as it was left', function (): void {
    app(ArchivePlan::class)->handle(planId('premium'));
    app(RestorePlan::class)->handle(planId('premium'));

    expect(collect(Plan::ladder())->pluck('code')->all())->toBe(['emprende', 'negocio', 'premium'])
        ->and(Plan::named('premium')->isArchived)->toBeFalse()
        ->and(Plan::named('premium')->price)->toBe(149);
});

test('the master archives and restores from its row, and says why a refusal happens', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $component = Livewire::test('catalog.plan')
        ->call('archive', planId('negocio'))
        ->assertDispatched('notify');

    expect(SubscriptionPlan::query()->where('code', 'negocio')->value('archived_at'))->toBeNull();

    $component->call('archive', planId('premium'));

    expect(SubscriptionPlan::query()->where('code', 'premium')->value('archived_at'))->not->toBeNull();

    $component->call('restore', planId('premium'));

    expect(SubscriptionPlan::query()->where('code', 'premium')->value('archived_at'))->toBeNull();
});
