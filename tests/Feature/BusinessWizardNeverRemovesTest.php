<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');
});

/*
|--------------------------------------------------------------------------
| The wizard adds, it never removes
|--------------------------------------------------------------------------
| A client takes a service or a product out only from the catalog screen, with
| an undo. In the wizard what is already saved has no remove button, cannot be
| removed by a request either, and Continuar never drops anything.
*/

function wizardOwner(): Business
{
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    test()->actingAs($user);

    return $business;
}

test('a saved service has no remove button and cannot be removed from the services step', function (): void {
    $business = wizardOwner();
    Service::factory()->for($business)->create(['name' => 'Consulta']);

    Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->assertSet('services', ['Consulta'])
        ->assertDontSeeHtml('wire:click="remove(0)"')
        // Not by the button, not by a forged request, not by un-picking its chip.
        ->call('remove', 0)
        ->assertSet('services', ['Consulta'])
        ->call('toggle', 'Consulta')
        ->assertSet('services', ['Consulta']);
});

test('a service just typed can still be taken back, because it is only a typo until saved', function (): void {
    $business = wizardOwner();
    Service::factory()->for($business)->create(['name' => 'Consulta']);

    Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->set('draft', 'Consluta')
        ->call('add')
        ->assertSet('services', ['Consulta', 'Consluta'])
        ->assertSeeHtml('wire:click="remove(1)"')
        ->call('remove', 1)
        ->assertSet('services', ['Consulta']);
});

test('once saved, what was typed can no longer be removed', function (): void {
    $business = wizardOwner();

    Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->set('draft', 'Control')
        ->call('add')
        ->call('finish')
        ->assertSet('savedServices', ['Control'])
        ->call('remove', 0)
        ->assertSet('services', ['Control']);

    expect($business->serviceNames())->toBe(['Control']);
});

test('Continuar on the services step never drops a stored service, whatever the screen sends', function (): void {
    $business = wizardOwner();
    Service::factory()->for($business)->create(['name' => 'Consulta']);
    Service::factory()->for($business)->create(['name' => 'Control']);

    // The list is forged down to one new name: the two stored ones must survive.
    Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->set('services', ['Urgencia'])
        ->call('finish');

    expect($business->serviceNames())->toContain('Consulta', 'Control', 'Urgencia');
});

test('a saved product has no remove button and cannot be removed from the products step', function (): void {
    $business = wizardOwner();
    Product::factory()->for($business)->create(['name' => 'Camilla']);

    Livewire::test('business.step-products', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->assertSet('products', ['Camilla'])
        ->assertDontSeeHtml('wire:click="remove(0)"')
        ->call('remove', 0)
        ->assertSet('products', ['Camilla'])
        ->call('toggle', 'Camilla')
        ->assertSet('products', ['Camilla']);
});

test('Continuar on the products step never drops a stored product, whatever the screen sends', function (): void {
    $business = wizardOwner();
    Product::factory()->for($business)->create(['name' => 'Camilla']);
    Product::factory()->for($business)->create(['name' => 'Estetoscopio']);

    Livewire::test('business.step-products', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->set('products', ['Tensiómetro'])
        ->call('finish');

    expect($business->productNames())->toContain('Camilla', 'Estetoscopio', 'Tensiómetro');
});
