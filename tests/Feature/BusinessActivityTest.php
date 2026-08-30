<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Country;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an activity belongs to a sector and a sector lists its activities', function (): void {
    $sector = BusinessSector::factory()->create(['name' => 'Salud']);
    $activity = BusinessActivity::factory()->for($sector, 'sector')->create(['name' => 'Farmacia']);

    expect($activity->sector->name)->toBe('Salud')
        ->and($sector->activities->pluck('name')->all())->toBe(['Farmacia']);
});

test('a business declares a primary activity and the sector is reached through it', function (): void {
    $activity = BusinessActivity::factory()
        ->for(BusinessSector::factory()->create(['name' => 'Gastronomía']), 'sector')
        ->create(['name' => 'Panadería']);

    $business = Business::factory()->create();
    $business->syncActivities($activity->id);

    expect($business->primaryActivity()->name)->toBe('Panadería')
        ->and($business->primaryActivity()->sector->name)->toBe('Gastronomía')
        ->and($activity->businesses)->toHaveCount(1);
});

test('a business can exist without any activity', function (): void {
    $business = Business::factory()->create();

    expect($business->activities)->toBeEmpty()
        ->and($business->primaryActivity())->toBeNull();
});

test('the same activity name can repeat across sectors but not inside one', function (): void {
    $beauty = BusinessSector::factory()->create(['name' => 'Belleza']);
    $services = BusinessSector::factory()->create(['name' => 'Servicios']);

    BusinessActivity::factory()->for($beauty, 'sector')->create(['name' => 'Estética', 'code' => 'estetica']);
    BusinessActivity::factory()->for($services, 'sector')->create(['name' => 'Estética', 'code' => 'estetica-servicios']);

    expect(BusinessActivity::where('name', 'Estética')->count())->toBe(2);

    expect(fn () => BusinessActivity::factory()->for($beauty, 'sector')->create(['name' => 'Estética', 'code' => 'otra']))
        ->toThrow(QueryException::class);
});

test('the activity code is unique across the whole table because it keys the assistant profile', function (): void {
    BusinessActivity::factory()->create(['code' => 'farmacia']);

    expect(fn () => BusinessActivity::factory()->create(['code' => 'farmacia']))
        ->toThrow(QueryException::class);
});

test('codes are stored lowercase and names keep their casing', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'SALUD', 'name' => '  Salud  Integral ']);

    expect($sector->code)->toBe('salud')
        ->and($sector->name)->toBe('Salud Integral');
});

test('a sector with activities cannot be wiped from the database', function (): void {
    $sector = BusinessSector::factory()->create();
    BusinessActivity::factory()->for($sector, 'sector')->create();

    // The restrictive FK protects the REAL deletion of the row.
    expect(fn () => $sector->forceDelete())->toThrow(QueryException::class);
});

test('soft deleting a sector with activities passes the database and needs an app level rule', function (): void {
    $sector = BusinessSector::factory()->create();
    BusinessActivity::factory()->for($sector, 'sector')->create();

    // A soft delete is an UPDATE and never touches the FK. Stopping a sector
    // with activities from being deactivated is an application rule, not yet
    // written; this test pins the real behaviour until it is.
    $sector->delete();

    expect($sector->fresh()->trashed())->toBeTrue()
        ->and(BusinessActivity::where('business_sector_id', $sector->id)->count())->toBe(1);
});

test('an activity used by a business cannot be wiped from the database', function (): void {
    $activity = BusinessActivity::factory()->create();
    Business::factory()->create()->syncActivities($activity->id);

    expect(fn () => $activity->forceDelete())->toThrow(QueryException::class);
});

test('the country relation on business still works', function (): void {
    $business = Business::factory()->for(Country::factory()->create(['name' => 'Argentina']))->create();

    expect($business->country->name)->toBe('Argentina');
});
