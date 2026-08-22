<?php

declare(strict_types=1);

use App\Models\Province;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    $region = Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $province->id]);

    $component = Livewire::test('catalog.region')->call('openEdit', $region->id);

    expect($component->get('form.recordId'))->toBe($region->id)
        ->and($component->get('form.data')->name)->toBe('Zona Norte')
        ->and($component->get('form.data')->province_id)->toBe($province->id);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.name', 'Zona Noroeste')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(Region::query()->count())->toBe(1)
        ->and($region->fresh()->name)->toBe('Zona Noroeste');
});

test('keeping its own name while editing does not trip the scoped unique rule', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($region->fresh()->is_active)->toBeFalse();
});

test('taking a name that already belongs to another region of the same province is rejected', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $province->id]);
    $region = Region::factory()->create(['name' => 'Zona Sur', 'province_id' => $province->id]);

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.name', 'Zona Norte')
        ->call('update')
        ->assertHasErrors('name')
        ->assertReturned(false);

    expect($region->fresh()->name)->toBe('Zona Sur');
});

test('moving a region to another province that already has that name is rejected', function (): void {
    $buenosAires = Province::factory()->create(['name' => 'Buenos Aires']);
    $santaFe = Province::factory()->create(['name' => 'Santa Fe']);
    Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $santaFe->id]);
    $region = Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $buenosAires->id]);

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.province_id', $santaFe->id)
        ->call('update')
        ->assertHasErrors('name');

    expect($region->fresh()->province_id)->toBe($buenosAires->id);
});

test('the province id posted by the combobox as a string is stored as the right integer', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);
    $other = Province::factory()->create(['name' => 'Santa Fe']);

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.province_id', (string) $other->id)
        ->call('update')
        ->assertHasNoErrors();

    expect($region->fresh()->province_id)->toBe($other->id);
});

test('clearing the province reports a validation error instead of killing the component', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);
    $originalProvinceId = $region->province_id;

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.province_id', '')
        ->call('update')
        ->assertHasErrors('province_id');

    expect($region->fresh()->province_id)->toBe($originalProvinceId);
});

test('starting a new region clears the record left over from an edit', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);

    $component = Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->call('openCreate');

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->name)->toBe('')
        ->and($component->get('form.data')->province_id)->toBeNull();
});

test('opening a region that no longer exists warns instead of blowing up', function (): void {
    Livewire::test('catalog.region')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    Livewire::test('catalog.region')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the feminine', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);

    Livewire::test('catalog.region')
        ->call('openEdit', $region->id)
        ->set('form.data.name', 'Zona Noroeste')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Región actualizada correctamente');
});
