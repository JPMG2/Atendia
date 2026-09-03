<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\ServiceType;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('a business declares one primary activity and as many secondary ones as it wants', function (): void {
    $bakery = BusinessActivity::factory()->create(['name' => 'Panadería']);
    $cafe = BusinessActivity::factory()->create(['name' => 'Cafetería']);
    $catering = BusinessActivity::factory()->create(['name' => 'Catering']);

    $business = Business::factory()->create();
    $business->syncActivities($bakery->id, [$cafe->id, $catering->id]);

    expect($business->activities)->toHaveCount(3)
        ->and($business->primaryActivity()->name)->toBe('Panadería');
});

test('the primary one comes first, then the secondary ones in the order they were given', function (): void {
    $primary = BusinessActivity::factory()->create(['name' => 'Panadería']);
    $second = BusinessActivity::factory()->create(['name' => 'Cafetería']);
    $third = BusinessActivity::factory()->create(['name' => 'Catering']);

    $business = Business::factory()->create();
    $business->syncActivities($primary->id, [$second->id, $third->id]);

    expect($business->activities->pluck('name')->all())
        ->toBe(['Panadería', 'Cafetería', 'Catering']);
});

test('a business cannot have two primary activities', function (): void {
    // Guarded by a partial unique index, not by application code: the primary is
    // what drives the assistant's tone and the trade knowledge pack, so two of
    // them is a state the database must refuse.
    $first = BusinessActivity::factory()->create();
    $second = BusinessActivity::factory()->create();
    $business = Business::factory()->create();

    $business->activities()->attach($first, ['is_primary' => true]);

    expect(fn (): mixed => $business->activities()->attach($second, ['is_primary' => true]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('many secondary activities coexist, which a plain unique would have blocked', function (): void {
    // A unique(business_id, is_primary) would forbid two secondaries. The partial
    // index only indexes the primary rows, so this has to work.
    $business = Business::factory()->create();
    $business->syncActivities(null, [
        BusinessActivity::factory()->create()->id,
        BusinessActivity::factory()->create()->id,
        BusinessActivity::factory()->create()->id,
    ]);

    expect($business->activities)->toHaveCount(3)
        ->and($business->primaryActivity())->toBeNull();
});

test('the same activity cannot be declared twice by one business', function (): void {
    $activity = BusinessActivity::factory()->create();
    $business = Business::factory()->create();

    $business->activities()->attach($activity);

    expect(fn (): mixed => $business->activities()->attach($activity))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('passing the primary among the secondary ones does not duplicate it', function (): void {
    $activity = BusinessActivity::factory()->create();
    $business = Business::factory()->create();

    $business->syncActivities($activity->id, [$activity->id]);

    expect($business->activities)->toHaveCount(1)
        ->and($business->primaryActivity()->id)->toBe($activity->id);
});

test('syncing replaces the previous declaration instead of piling up', function (): void {
    $old = BusinessActivity::factory()->create();
    $new = BusinessActivity::factory()->create();
    $business = Business::factory()->create();

    $business->syncActivities($old->id);
    $business->syncActivities($new->id);

    expect($business->fresh()->activities->pluck('id')->all())->toBe([$new->id]);
});

test('deleting a business takes its declarations with it', function (): void {
    $business = Business::factory()->create();
    $business->syncActivities(BusinessActivity::factory()->create()->id);

    $business->forceDelete();

    expect(DB::table('activity_business')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The whole point: a second activity unlocks what the first never suggested
|--------------------------------------------------------------------------
*/

test('a bakery is suggested only its own service types', function (): void {
    seedBusinessCatalog();

    $business = Business::factory()->create();
    $business->syncActivities(activityId('panaderia'));

    expect($business->suggestedServiceTypes()->pluck('code')->all())
        // Ordered by the type's own sort_order, not by the activity.
        ->toBe(['pedido-llevar', 'producto-mostrador'])
        ->not->toContain('mesa');
});

// The sector's twin of the business method: same union via activities, used
// by the wizard before any business exists. The type's own sector column is
// admin grouping and takes no part.
test('a sector suggests the union of what its activities suggest', function (): void {
    seedBusinessCatalog();

    $sector = BusinessSector::where('code', 'gastronomia')->sole();

    expect($sector->suggestedServiceTypes()->pluck('code')->all())
        ->toContain('plato')
        ->toContain('mesa')
        ->toContain('pedido-llevar')
        ->not->toContain('consulta');
});

test('adding a second activity unlocks its types, with no code exception anywhere', function (): void {
    // The bakery that puts tables in declares "Cafetería" and from then on sees
    // the dining-room types. Nobody unblocked anything by hand: it is the union
    // of what each of its activities suggests.
    seedBusinessCatalog();

    $business = Business::factory()->create();
    $business->syncActivities(activityId('panaderia'), [activityId('cafeteria')]);

    expect($business->suggestedServiceTypes()->pluck('code')->all())
        ->toContain('mesa')
        ->toContain('plato')
        ->toContain('producto-mostrador');
});

test('the suggestion is a union across activities, never an intersection', function (): void {
    // An intersection would leave the bakery that also serves coffee with only
    // what both trades share, the opposite of what a second activity means.
    seedBusinessCatalog();

    $business = Business::factory()->create();
    $business->syncActivities(activityId('panaderia'), [activityId('cafeteria')]);

    $bakeryOnly = Business::factory()->create();
    $bakeryOnly->syncActivities(activityId('panaderia'));

    expect($business->suggestedServiceTypes()->count())
        ->toBeGreaterThan($bakeryOnly->suggestedServiceTypes()->count());
});

test('a suggestion is not a permission: an unsuggested type stays reachable', function (): void {
    seedBusinessCatalog();

    $business = Business::factory()->create();
    $business->syncActivities(activityId('peluqueria'));

    // Nothing suggests a haircut shop a takeaway order, and nothing forbids the
    // type from existing and being listed either.
    expect($business->suggestedServiceTypes()->pluck('code')->all())->not->toContain('pedido-llevar')
        ->and(ServiceType::query()->where('code', 'pedido-llevar')->where('is_active', true)->exists())->toBeTrue();
});

test('an inactive type is not suggested to anyone', function (): void {
    seedBusinessCatalog();
    ServiceType::query()->where('code', 'mesa')->update(['is_active' => false]);

    $business = Business::factory()->create();
    $business->syncActivities(activityId('restaurante'));

    expect($business->suggestedServiceTypes()->pluck('code')->all())->not->toContain('mesa');
});

test('a business with no activity is suggested nothing', function (): void {
    seedBusinessCatalog();

    expect(Business::factory()->create()->suggestedServiceTypes())->toBeEmpty();
});

function seedBusinessCatalog(): void
{
    foreach ([BusinessSectorSeeder::class, BusinessActivitySeeder::class, ServiceModalitySeeder::class, ServiceAttributeSeeder::class, ServiceTypeSeeder::class] as $seeder) {
        (new $seeder)->run();
    }
}

function activityId(string $code): int
{
    return BusinessActivity::query()->where('code', $code)->value('id');
}
