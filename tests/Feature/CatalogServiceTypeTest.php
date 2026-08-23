<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.service-type')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Consulta')
        ->assertSet('form.data.name', 'Consulta');
});

test('the type table hands its rows to Alpine so the search filters client-side', function (): void {
    $type = ServiceType::factory()->create(['name' => 'Consulta']);

    $html = Livewire::test('catalog.service-type')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($type->name);
});

test('every row carries its id, because the name is user-editable and cannot identify a row', function (): void {
    $type = ServiceType::factory()->create();

    $html = Livewire::test('catalog.service-type')->html();

    expect($html)->toContain(':key="row.id"');

    expect(railConfig($html, 'items'))->toHaveCount(1)
        ->and(railConfig($html, 'items')[0]['id'])->toBe($type->id);
});

test('the type editor renders its real inputs', function (): void {
    Livewire::test('catalog.service-type')
        ->assertSee('Clave')
        ->assertSee('Modalidad')
        ->assertSee('Rubro')
        ->assertSee('Descripción')
        ->assertSee('Orden')
        ->assertSee('Estado');
});

test('the row flattens the modality and the sector to their readable names', function (): void {
    $modality = ServiceModality::factory()->create(['name' => 'Cita / Turno']);
    $sector = BusinessSector::factory()->create(['name' => 'Salud']);
    ServiceType::factory()->create([
        'name' => 'Consulta',
        'service_modality_id' => $modality->id,
        'business_sector_id' => $sector->id,
    ]);

    $rows = Livewire::test('catalog.service-type')->get('initialRows');

    expect($rows[0]['modality'])->toBe('Cita / Turno')
        ->and($rows[0]['sector'])->toBe('Salud');
});

test('a type without a sector shows an empty cell, not a null', function (): void {
    // The sector is optional: a cross-trade type belongs to no group. The row
    // vocabulary says a null travels as ''.
    ServiceType::factory()->create(['business_sector_id' => null]);

    $rows = Livewire::test('catalog.service-type')->get('initialRows');

    expect($rows[0]['sector'])->toBe('');
});

/*
|--------------------------------------------------------------------------
| store — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a type is created with all of its attributes', function (): void {
    $modality = ServiceModality::factory()->create();
    $sector = BusinessSector::factory()->create();

    Livewire::test('catalog.service-type')
        ->set('form.data.code', 'consulta')
        ->set('form.data.name', 'Consulta')
        ->set('form.data.description', 'Atención con turno.')
        ->set('form.data.service_modality_id', $modality->id)
        ->set('form.data.business_sector_id', $sector->id)
        ->set('form.data.sort_order', 1)
        ->call('create')
        ->assertHasNoErrors();

    $type = ServiceType::query()->firstWhere('code', 'consulta');

    expect($type)->not->toBeNull()
        ->and($type->name)->toBe('Consulta')
        ->and($type->service_modality_id)->toBe($modality->id)
        ->and($type->business_sector_id)->toBe($sector->id);
});

test('a type cannot exist without a modality, because nothing would know how to offer it', function (): void {
    Livewire::test('catalog.service-type')
        ->set('form.data.code', 'consulta')
        ->set('form.data.name', 'Consulta')
        ->call('create')
        ->assertHasErrors('service_modality_id');
});

test('the sector is optional, because a type can be cross-trade', function (): void {
    $modality = ServiceModality::factory()->create();

    Livewire::test('catalog.service-type')
        ->set('form.data.code', 'consulta')
        ->set('form.data.name', 'Consulta')
        ->set('form.data.service_modality_id', $modality->id)
        ->call('create')
        ->assertHasNoErrors();

    expect(ServiceType::query()->firstWhere('code', 'consulta')->business_sector_id)->toBeNull();
});

test('two types cannot share a code, because the code is what the assistant references', function (): void {
    ServiceType::factory()->create(['code' => 'consulta']);
    $modality = ServiceModality::factory()->create();

    Livewire::test('catalog.service-type')
        ->set('form.data.code', 'consulta')
        ->set('form.data.name', 'Otra cosa')
        ->set('form.data.service_modality_id', $modality->id)
        ->call('create')
        ->assertHasErrors('code');

    expect(ServiceType::query()->where('code', 'consulta')->count())->toBe(1);
});

test('the attribute column shows the label of this type, not the global one', function (): void {
    // The override exists so a table says "Comensales"; printing the global name
    // here would make it look like the override was not applied.
    $type = ServiceType::factory()->create();
    $people = ServiceAttribute::factory()->create(['name' => 'Personas']);
    $zone = ServiceAttribute::factory()->create(['name' => 'Zona']);

    $type->serviceAttributes()->attach($people, ['sort_order' => 1, 'label_override' => 'Comensales']);
    $type->serviceAttributes()->attach($zone, ['sort_order' => 2]);

    $rows = Livewire::test('catalog.service-type')->get('initialRows');

    // Lista, no un string con comas: la celda las pinta como pastillas.
    expect($rows[0]['attributes'])->toBe(['Comensales', 'Zona']);
});

test('many types share one modality without breaking the relation', function (): void {
    // Plato and Combo are two different types with the same modality. That is
    // what many-to-one means; what would break it is one type with two.
    $producto = ServiceModality::factory()->create(['name' => 'Producto']);

    ServiceType::factory()->create(['name' => 'Plato', 'service_modality_id' => $producto->id]);
    ServiceType::factory()->create(['name' => 'Combo', 'service_modality_id' => $producto->id]);

    expect($producto->types()->count())->toBe(2)
        ->and($producto->isInUse())->toBeTrue();
});
