<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Wizard step 4 — products persistence
|--------------------------------------------------------------------------
| Continuar reconciles the tenant's rows with the on-screen list through
| BusinessForm::saveProducts(): only the name lands here (the universal
| core), dropped names leave softly and skipping writes nothing. The drop
| zone stays a simulation until the import slice.
*/

function actingAsShopOwner(): Business
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create()->refresh();
    $user->assignRole('client');

    $business = Business::factory()->create();
    $user->business()->associate($business)->save();

    test()->actingAs($user);

    return $business;
}

test('finishing writes the on-screen list to the tenant', function (): void {
    $business = actingAsShopOwner();

    Livewire::test('business.step-products')
        ->call('add', 'Pan de campo')
        ->call('add', 'Alfajor triple')
        ->call('finish')
        ->assertDispatched('wizard:step-completed', step: 4);

    expect($business->products()->pluck('name')->all())->toBe(['Pan de campo', 'Alfajor triple']);
});

test('a product dropped from the list leaves softly on finish', function (): void {
    $business = actingAsShopOwner();
    $business->products()->create(['name' => 'Pan de campo']);
    $gone = $business->products()->create(['name' => 'Factura']);

    Livewire::test('business.step-products')
        ->call('remove', 1)
        ->call('finish');

    expect(Product::withTrashed()->find($gone->id)->trashed())->toBeTrue()
        ->and($business->products()->pluck('name')->all())->toBe(['Pan de campo']);
});

test('a removed name coming back restores the same row, not a duplicate', function (): void {
    $business = actingAsShopOwner();
    $product = $business->products()->create(['name' => 'Pan de campo']);
    $product->delete();

    Livewire::test('business.step-products')
        ->call('add', 'Pan de campo')
        ->call('finish');

    expect($business->products()->count())->toBe(1)
        ->and($product->fresh()->trashed())->toBeFalse();
});

test('skipping the step writes nothing, as promised', function (): void {
    actingAsShopOwner();

    Livewire::test('business.step-products')
        ->call('add', 'Pan de campo')
        ->call('finish', true)
        ->assertDispatched('wizard:step-completed', step: 4, skipped: true);

    expect(Product::query()->count())->toBe(0);
});

test('re-entering the step reloads what a previous pass saved', function (): void {
    $business = actingAsShopOwner();
    $business->products()->create(['name' => 'Pan de campo']);

    Livewire::test('business.step-products')
        ->assertSet('products', ['Pan de campo'])
        ->assertDispatched('wizard:products-updated');
});

test('without a business the step warns instead of writing', function (): void {
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create()->refresh();
    $user->assignRole('client');
    test()->actingAs($user);

    Livewire::test('business.step-products')
        ->call('add', 'Pan de campo')
        ->call('finish')
        ->assertNotDispatched('wizard:step-completed');

    expect(Product::query()->count())->toBe(0);
});

test('the preview asks for the last real product, not the canned joke', function (): void {
    actingAsShopOwner();

    Livewire::test('business.wizard')
        ->dispatch('wizard:name-updated', name: 'Kiosco Lito')
        ->dispatch('wizard:products-updated', products: ['Alfajor triple'])
        ->assertDispatched('preview-updated', function (string $event, array $params): bool {
            $conversation = json_encode($params['messages']);

            return str_contains($conversation, 'alfajor triple')
                && ! str_contains($conversation, 'Fiat Palio');
        });
});
