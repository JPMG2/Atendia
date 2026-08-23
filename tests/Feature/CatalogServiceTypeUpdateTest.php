<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $modality = ServiceModality::factory()->create();
    $sector = BusinessSector::factory()->create();
    $type = ServiceType::factory()->create([
        'code' => 'consulta',
        'name' => 'Consulta',
        'service_modality_id' => $modality->id,
        'business_sector_id' => $sector->id,
        'sort_order' => 1,
    ]);

    $component = Livewire::test('catalog.service-type')->call('openEdit', $type->id);

    expect($component->get('form.recordId'))->toBe($type->id)
        ->and($component->get('form.data')->code)->toBe('consulta')
        ->and($component->get('form.data')->service_modality_id)->toBe($modality->id)
        ->and($component->get('form.data')->business_sector_id)->toBe($sector->id);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $type = ServiceType::factory()->create(['code' => 'consulta', 'name' => 'Consulta']);

    Livewire::test('catalog.service-type')
        ->call('openEdit', $type->id)
        ->set('form.data.name', 'Primera consulta')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(ServiceType::query()->count())->toBe(1)
        ->and($type->fresh()->name)->toBe('Primera consulta');
});

test('the modality can be changed, because it is the type that moves and not its attributes', function (): void {
    $type = ServiceType::factory()->create();
    $other = ServiceModality::factory()->create();

    Livewire::test('catalog.service-type')
        ->call('openEdit', $type->id)
        ->set('form.data.service_modality_id', $other->id)
        ->call('update')
        ->assertHasNoErrors();

    expect($type->fresh()->service_modality_id)->toBe($other->id);
});

test('the sector can be cleared, leaving a cross-trade type', function (): void {
    $type = ServiceType::factory()->create(['business_sector_id' => BusinessSector::factory()]);

    Livewire::test('catalog.service-type')
        ->call('openEdit', $type->id)
        ->set('form.data.business_sector_id', '')
        ->call('update')
        ->assertHasNoErrors();

    expect($type->fresh()->business_sector_id)->toBeNull();
});

test('keeping its own code and name while editing does not trip the unique rules', function (): void {
    $type = ServiceType::factory()->create(['code' => 'consulta', 'name' => 'Consulta']);

    Livewire::test('catalog.service-type')
        ->call('openEdit', $type->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($type->fresh()->is_active)->toBeFalse();
});

test('taking a code that already belongs to another type is rejected', function (): void {
    ServiceType::factory()->create(['code' => 'consulta', 'name' => 'Consulta']);
    $type = ServiceType::factory()->create(['code' => 'estudio', 'name' => 'Estudio']);

    Livewire::test('catalog.service-type')
        ->call('openEdit', $type->id)
        ->set('form.data.code', 'consulta')
        ->call('update')
        ->assertHasErrors('code');

    expect($type->fresh()->code)->toBe('estudio');
});

test('opening a record that no longer exists leaves the user on the list', function (): void {
    Livewire::test('catalog.service-type')
        ->call('openEdit', 9999)
        ->assertReturned(false)
        ->assertDispatched('notify');
});
