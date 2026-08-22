<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);
    $activity = BusinessActivity::factory()->for($sector, 'sector')->create([
        'code' => 'panaderia',
        'name' => 'Panadería',
        'description' => 'Pan y facturas',
        'sort_order' => 3,
    ]);

    $component = Livewire::test('catalog.business-activity')->call('openEdit', $activity->id);

    expect($component->get('form.recordId'))->toBe($activity->id)
        ->and($component->get('form.data')->business_sector_id)->toBe($sector->id)
        ->and($component->get('form.data')->code)->toBe('panaderia')
        ->and($component->get('form.data')->name)->toBe('Panadería')
        ->and($component->get('form.data')->description)->toBe('Pan y facturas')
        ->and($component->get('form.data')->sort_order)->toBe(3);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.name', 'Panadería y pastelería')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(BusinessActivity::query()->count())->toBe(1)
        ->and($activity->fresh()->name)->toBe('Panadería y pastelería');
});

test('keeping its own code and name while editing does not trip the unique rules', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($activity->fresh()->is_active)->toBeFalse();
});

test('taking a name that already belongs to another activity of the same sector is rejected', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);
    BusinessActivity::factory()->for($sector, 'sector')->create(['code' => 'panaderia', 'name' => 'Panadería']);
    $activity = BusinessActivity::factory()->for($sector, 'sector')->create(['code' => 'restaurante', 'name' => 'Restaurante']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.name', 'Panadería')
        ->call('update')
        ->assertHasErrors('name')
        ->assertReturned(false);

    expect($activity->fresh()->name)->toBe('Restaurante');
});

test('moving an activity to another sector that already has that name is rejected', function (): void {
    // "Estética" is legitimate in Belleza and in Servicios at the same time, but
    // one sector cannot hold two. The unique is scoped by sector, so the clash
    // only appears once the record lands in the other sector — the rule has to
    // read the sector being SAVED, not the one the record had.
    $beauty = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza']);
    $services = BusinessSector::factory()->create(['code' => 'servicios', 'name' => 'Servicios']);
    BusinessActivity::factory()->for($beauty, 'sector')->create(['code' => 'estetica', 'name' => 'Estética']);
    $activity = BusinessActivity::factory()->for($services, 'sector')->create(['code' => 'estetica-servicios', 'name' => 'Estética']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.business_sector_id', $beauty->id)
        ->call('update')
        ->assertHasErrors('name');

    expect($activity->fresh()->business_sector_id)->toBe($services->id);
});

test('taking a code that already belongs to another activity is rejected', function (): void {
    BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);
    $activity = BusinessActivity::factory()->create(['code' => 'restaurante', 'name' => 'Restaurante']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.code', 'panaderia')
        ->call('update')
        ->assertHasErrors('code');

    expect($activity->fresh()->code)->toBe('restaurante');
});

test('the sector id posted by the combobox as a string is stored as the right integer', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);
    $other = BusinessSector::factory()->create(['code' => 'comercio', 'name' => 'Comercio']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.business_sector_id', (string) $other->id)
        ->call('update')
        ->assertHasNoErrors();

    expect($activity->fresh()->business_sector_id)->toBe($other->id);
});

test('clearing the sector reports a validation error instead of killing the component', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);
    $originalSectorId = $activity->business_sector_id;

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.business_sector_id', '')
        ->call('update')
        ->assertHasErrors('business_sector_id');

    expect($activity->fresh()->business_sector_id)->toBe($originalSectorId);
});

test('starting a new activity clears the record left over from an edit', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);

    $component = Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->call('openCreate');

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->code)->toBe('')
        ->and($component->get('form.data')->business_sector_id)->toBeNull();
});

test('opening an activity that no longer exists warns instead of blowing up', function (): void {
    Livewire::test('catalog.business-activity')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    Livewire::test('catalog.business-activity')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the feminine', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.name', 'Panadería y pastelería')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Actividad actualizada correctamente');
});
