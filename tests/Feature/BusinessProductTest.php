<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Product;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business's own products
|--------------------------------------------------------------------------
| Tenant data, the universal core every trade shares: each business names
| what it sells — Pan de campo, Alternador Palio 1.4 — and only the name is
| mandatory. Columns the core does not know belong in the product's
| knowledge, never here.
*/

test('a product needs only its name: price, stock and description stay optional', function (): void {
    $product = Product::factory()->create(['name' => 'Pan de campo']);

    expect($product->refresh())
        ->name->toBe('Pan de campo')
        ->price->toBeNull()
        ->stock->toBeNull()
        ->description->toBeNull()
        ->is_active->toBeTrue();
});

test('stock takes fractions, because a deli sells kilos while a kiosk counts units', function (): void {
    $product = Product::factory()->create(['price' => '1500.50', 'stock' => '3.25']);

    expect($product->refresh())
        ->price->toBe('1500.50')
        ->stock->toBe('3.25');
});

test('a business cannot list the same product twice, but two businesses can share the name', function (): void {
    $bakery = Business::factory()->create();
    $rival = Business::factory()->create();

    Product::factory()->create(['business_id' => $bakery->id, 'name' => 'Pan de campo']);

    // Another tenant naming its own "Pan de campo" is the normal world.
    Product::factory()->create(['business_id' => $rival->id, 'name' => 'Pan de campo']);

    expect(fn () => Product::factory()->create([
        'business_id' => $bakery->id,
        'name' => 'Pan de campo',
    ]))->toThrow(QueryException::class);
});

test('the tenant cannot be rewritten through mass assignment', function (): void {
    // Same boundary as Service: a product is created THROUGH its owner, so a
    // crafted request must never move it to another business.
    $product = Product::factory()->create();
    $intruder = Business::factory()->create();

    expect(fn () => $product->fill(['business_id' => $intruder->id]))
        ->toThrow(MassAssignmentException::class);
});

test('a product is created through its owner', function (): void {
    $business = Business::factory()->create();

    $product = $business->products()->create(['name' => 'Alternador Palio 1.4']);

    expect($business->products()->pluck('name')->all())->toBe(['Alternador Palio 1.4'])
        ->and($product->business->is($business))->toBeTrue();
});
