<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Service;
use App\Models\SuggestedService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Wizard step 3 — services persistence
|--------------------------------------------------------------------------
| Continuar reconciles the tenant's rows with the on-screen list through
| BusinessForm::saveServices(): suggested names adopt their catalog type,
| unknown ones stay untyped, dropped ones leave softly and skipping writes
| nothing. These tests pin that contract.
*/

function actingAsOwner(): Business
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create()->refresh();
    $user->assignRole('client');

    $business = Business::factory()->create();
    $user->business()->associate($business)->save();

    test()->actingAs($user);

    return $business;
}

test('finishing writes the list and a suggested name adopts its type', function (): void {
    $business = actingAsOwner();
    $suggestion = SuggestedService::factory()->create(['name' => 'Corte de caballero']);

    Livewire::test('business.step-services')
        ->call('add', 'Corte de caballero')
        ->call('add', 'Ecodoppler')
        ->call('finish')
        ->assertDispatched('wizard:step-completed', step: 3);

    expect($business->services()->where('name', 'Corte de caballero')->value('service_type_id'))
        ->toBe($suggestion->service_type_id)
        ->and($business->services()->where('name', 'Ecodoppler')->first()?->service_type_id)->toBeNull();
});

test('a service dropped from the list leaves softly on finish', function (): void {
    $business = actingAsOwner();
    $business->services()->create(['name' => 'Manicura']);
    $gone = $business->services()->create(['name' => 'Pedicura']);

    Livewire::test('business.step-services')
        ->call('remove', 1)
        ->call('finish');

    expect(Service::withTrashed()->find($gone->id)->trashed())->toBeTrue()
        ->and($business->services()->pluck('name')->all())->toBe(['Manicura']);
});

test('a removed name coming back restores the same row, not a duplicate', function (): void {
    $business = actingAsOwner();
    $service = $business->services()->create(['name' => 'Manicura']);
    $service->delete();

    Livewire::test('business.step-services')
        ->call('add', 'Manicura')
        ->call('finish');

    expect($business->services()->count())->toBe(1)
        ->and($service->fresh()->trashed())->toBeFalse();
});

test('skipping the step writes nothing, as promised', function (): void {
    actingAsOwner();

    Livewire::test('business.step-services')
        ->call('add', 'Ecodoppler')
        ->call('finish', true)
        ->assertDispatched('wizard:step-completed', step: 3, skipped: true);

    expect(Service::query()->count())->toBe(0);
});

test('re-entering the step reloads what a previous pass saved', function (): void {
    $business = actingAsOwner();
    $business->services()->create(['name' => 'Manicura']);

    Livewire::test('business.step-services')
        ->assertSet('services', ['Manicura'])
        ->assertDispatched('wizard:services-updated');
});

test('without a business the step warns instead of writing', function (): void {
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create()->refresh();
    $user->assignRole('client');
    test()->actingAs($user);

    Livewire::test('business.step-services')
        ->call('add', 'Ecodoppler')
        ->call('finish')
        ->assertNotDispatched('wizard:step-completed');

    expect(Service::query()->count())->toBe(0);
});
