<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $sector = BusinessSector::factory()->create([
        'code' => 'salud',
        'name' => 'Salud',
        'description' => 'Atención de la salud',
        'sort_order' => 2,
    ]);

    $component = Livewire::test('catalog.business-sector')->call('openEdit', $sector->id);

    expect($component->get('form.businessSectorId'))->toBe($sector->id)
        ->and($component->get('form.businessSectorData')->code)->toBe('salud')
        ->and($component->get('form.businessSectorData')->name)->toBe('Salud')
        ->and($component->get('form.businessSectorData')->description)->toBe('Atención de la salud')
        ->and($component->get('form.businessSectorData')->sort_order)->toBe(2);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);

    Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->set('form.businessSectorData.name', 'Salud y bienestar')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(BusinessSector::query()->count())->toBe(1)
        ->and($sector->fresh()->name)->toBe('Salud y bienestar');
});

test('keeping its own code and name while editing does not trip the unique rules', function (): void {
    // The unique rules would reject the record against itself; the update path
    // has to ignore its own id.
    $sector = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);

    Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->set('form.businessSectorData.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($sector->fresh()->is_active)->toBeFalse();
});

test('taking a code that already belongs to another sector is rejected', function (): void {
    BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);
    $sector = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza']);

    Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->set('form.businessSectorData.code', 'salud')
        ->call('update')
        ->assertHasErrors('code')
        ->assertReturned(false);

    expect($sector->fresh()->code)->toBe('belleza');
});

test('taking a name that already belongs to another sector is rejected', function (): void {
    BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);
    $sector = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza']);

    Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->set('form.businessSectorData.name', 'Salud')
        ->call('update')
        ->assertHasErrors('name');

    expect($sector->fresh()->name)->toBe('Belleza');
});

test('clearing the description on an edit stores null instead of an empty string', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'salud', 'description' => 'Algo']);

    Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->set('form.businessSectorData.description', '')
        ->call('update')
        ->assertHasNoErrors();

    expect($sector->fresh()->description)->toBeNull();
});

test('starting a new sector clears the record left over from an edit', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);

    $component = Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->call('openCreate');

    expect($component->get('form.businessSectorId'))->toBeNull()
        ->and($component->get('form.businessSectorData')->code)->toBe('')
        ->and($component->get('form.businessSectorData')->name)->toBe('');
});

test('opening a sector that no longer exists warns instead of blowing up', function (): void {
    Livewire::test('catalog.business-sector')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    Livewire::test('catalog.business-sector')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the masculine', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);

    Livewire::test('catalog.business-sector')
        ->call('openEdit', $sector->id)
        ->set('form.businessSectorData.name', 'Salud y bienestar')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Rubro actualizado correctamente');
});
