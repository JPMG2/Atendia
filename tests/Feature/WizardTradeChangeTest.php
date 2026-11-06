<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\SuggestedServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');

    foreach ([
        BusinessSectorSeeder::class, BusinessActivitySeeder::class, ServiceModalitySeeder::class,
        ServiceAttributeSeeder::class, ServiceTypeSeeder::class, SuggestedServiceSeeder::class,
    ] as $seeder) {
        $this->seed($seeder);
    }
});

/*
|--------------------------------------------------------------------------
| Going back to step 2 and changing trade
|--------------------------------------------------------------------------
| The services and products the old trade put on the list leave with it. What
| the person typed herself, and what both trades suggest, stays.
*/

function ownerOf(string $activityCode): Business
{
    $business = Business::factory()->create();
    $business->syncActivities(BusinessActivity::where('code', $activityCode)->value('id'));

    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    test()->actingAs($user);

    return $business;
}

test('changing trade takes the old trade\'s services off the list and keeps what she typed', function (): void {
    $business = ownerOf('consultorio-medico');
    Service::factory()->for($business)->create(['name' => 'Consulta clínica']);
    Service::factory()->for($business)->create(['name' => 'Consulta a domicilio']);
    Service::factory()->for($business)->create(['name' => 'Sesión de bienestar propia']);

    // The business still holds the old trade; the step is told the new one, as after going back to step 2.
    Livewire::test('business.step-services', ['sector' => 'gastronomia', 'activity' => 'restaurante'])
        ->assertSet('services', ['Consulta clínica', 'Consulta a domicilio', 'Sesión de bienestar propia'])
        ->dispatch('wizard:step-completed', step: 2)
        ->assertSet('services', ['Sesión de bienestar propia'])
        ->assertDispatched('wizard:services-updated');
});

test('a name both trades suggest stays when the trade changes', function (): void {
    $business = ownerOf('consultorio-medico');
    // "Envío a domicilio" is offered by a pharmacy and by a restaurant alike.
    Service::factory()->for($business)->create(['name' => 'Envío a domicilio']);
    $business->syncActivities(BusinessActivity::where('code', 'farmacia')->value('id'));

    Livewire::test('business.step-services', ['sector' => 'gastronomia', 'activity' => 'restaurante'])
        ->dispatch('wizard:step-completed', step: 2)
        ->assertSet('services', ['Envío a domicilio']);
});

test('nothing leaves the list while the trade stays the same', function (): void {
    $business = ownerOf('consultorio-medico');
    Service::factory()->for($business)->create(['name' => 'Consulta clínica']);

    Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->dispatch('wizard:step-completed', step: 2)
        ->assertSet('services', ['Consulta clínica'])
        ->assertNotDispatched('wizard:services-updated');
});

test('a business with no trade yet loses nothing on its first pass', function (): void {
    $business = Business::factory()->create();
    Service::factory()->for($business)->create(['name' => 'Consulta clínica']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    $this->actingAs($user);

    Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->dispatch('wizard:step-completed', step: 2)
        ->assertSet('services', ['Consulta clínica']);
});

test('saving after the change drops the old trade\'s services from the business', function (): void {
    $business = ownerOf('consultorio-medico');
    Service::factory()->for($business)->create(['name' => 'Consulta clínica']);
    Service::factory()->for($business)->create(['name' => 'Sesión de bienestar propia']);

    Livewire::test('business.step-services', ['sector' => 'gastronomia', 'activity' => 'restaurante'])
        ->dispatch('wizard:step-completed', step: 2)
        ->call('finish');

    expect($business->serviceNames())->toBe(['Sesión de bienestar propia']);
});

test('changing trade takes the old trade\'s products off the list and keeps what she typed', function (): void {
    $business = ownerOf('farmacia');
    Product::factory()->for($business)->create(['name' => 'Perfumería y cuidado personal']);
    Product::factory()->for($business)->create(['name' => 'Camilla propia']);

    Livewire::test('business.step-products', ['sector' => 'gastronomia', 'activity' => 'restaurante'])
        ->assertSet('products', ['Perfumería y cuidado personal', 'Camilla propia'])
        ->dispatch('wizard:step-completed', step: 2)
        ->assertSet('products', ['Camilla propia'])
        ->assertDispatched('wizard:products-updated');
});
