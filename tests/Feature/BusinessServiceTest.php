<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business's own services
|--------------------------------------------------------------------------
| Tenant data, not a catalog master: each business names what it offers —
| Ecodoppler, Dobladillo, Torta de bodas — and points it at the catalog TYPE
| that lends it behavior. The bare name is the floor: enough for the
| assistant to answer "do you offer X?".
*/

test('a service needs only its name: price, length and description stay optional', function (): void {
    $service = Service::factory()->create(['name' => 'Ecodoppler']);

    expect($service->refresh())
        ->name->toBe('Ecodoppler')
        ->price->toBeNull()
        ->duration_minutes->toBeNull()
        ->description->toBeNull()
        ->is_active->toBeTrue();
});

test('price and length are welcome when the business does want to publish them', function (): void {
    $service = Service::factory()->create(['price' => '15000.50', 'duration_minutes' => 30]);

    expect($service->refresh())
        ->price->toBe('15000.50')
        ->duration_minutes->toBe(30);
});

test('a business cannot list the same service twice, but two businesses can share the name', function (): void {
    $bakery = Business::factory()->create();
    $rival = Business::factory()->create();
    $type = ServiceType::factory()->create();

    Service::factory()->create(['business_id' => $bakery->id, 'service_type_id' => $type->id, 'name' => 'Torta de bodas']);

    // Another tenant naming its own "Torta de bodas" is the normal world.
    Service::factory()->create(['business_id' => $rival->id, 'service_type_id' => $type->id, 'name' => 'Torta de bodas']);

    expect(fn () => Service::factory()->create([
        'business_id' => $bakery->id,
        'service_type_id' => $type->id,
        'name' => 'Torta de bodas',
    ]))->toThrow(QueryException::class);
});

test('the tenant cannot be rewritten through mass assignment', function (): void {
    // Same boundary as User::business_id: a service is created THROUGH its
    // owner, so a crafted request must never move it to another business.
    $service = Service::factory()->create();
    $intruder = Business::factory()->create();

    expect(fn () => $service->fill(['business_id' => $intruder->id]))
        ->toThrow(MassAssignmentException::class);
});

test('a service is created through its owner and borrows behavior from the catalog mould', function (): void {
    $business = Business::factory()->create();
    $type = ServiceType::factory()->create(['name' => 'Estudio']);

    $service = $business->services()->create(['service_type_id' => $type->id, 'name' => 'Ecodoppler']);

    expect($business->services()->pluck('name')->all())->toBe(['Ecodoppler'])
        ->and($service->serviceType->name)->toBe('Estudio')
        ->and($service->business->is($business))->toBeTrue();
});

test('a catalog type in use cannot be hard-deleted from under the services', function (): void {
    $service = Service::factory()->create();

    // forceDelete, because SoftDeletes never fires the FK — the restrict is
    // the last net for a real delete.
    expect(fn () => $service->serviceType->forceDelete())->toThrow(QueryException::class);
});
