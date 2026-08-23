<?php

declare(strict_types=1);

use App\Models\ServiceModality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $modality = ServiceModality::factory()->create([
        'code' => 'cita',
        'name' => 'Cita / Turno',
        'description' => 'Fecha, hora y duración.',
        'icon' => 'calendar-check',
        'sort_order' => 1,
    ]);

    $component = Livewire::test('catalog.service-modality')->call('openEdit', $modality->id);

    expect($component->get('form.recordId'))->toBe($modality->id)
        ->and($component->get('form.data')->code)->toBe('cita')
        ->and($component->get('form.data')->name)->toBe('Cita / Turno')
        ->and($component->get('form.data')->description)->toBe('Fecha, hora y duración.')
        ->and($component->get('form.data')->icon)->toBe('calendar-check')
        ->and($component->get('form.data')->sort_order)->toBe(1);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $modality = ServiceModality::factory()->create(['code' => 'cita', 'name' => 'Cita']);

    Livewire::test('catalog.service-modality')
        ->call('openEdit', $modality->id)
        ->set('form.data.name', 'Cita / Turno')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(ServiceModality::query()->count())->toBe(1)
        ->and($modality->fresh()->name)->toBe('Cita / Turno');
});

test('keeping its own code and name while editing does not trip the unique rules', function (): void {
    // The unique rules would reject the record against itself; the update path
    // has to ignore its own id.
    $modality = ServiceModality::factory()->create(['code' => 'cita', 'name' => 'Cita']);

    Livewire::test('catalog.service-modality')
        ->call('openEdit', $modality->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($modality->fresh()->is_active)->toBeFalse();
});

test('taking a code that already belongs to another modality is rejected', function (): void {
    ServiceModality::factory()->create(['code' => 'cita', 'name' => 'Cita']);
    $modality = ServiceModality::factory()->create(['code' => 'reserva', 'name' => 'Reserva']);

    Livewire::test('catalog.service-modality')
        ->call('openEdit', $modality->id)
        ->set('form.data.code', 'cita')
        ->call('update')
        ->assertHasErrors('code');

    expect($modality->fresh()->code)->toBe('reserva');
});

test('opening a record that no longer exists leaves the user on the list', function (): void {
    Livewire::test('catalog.service-modality')
        ->call('openEdit', 9999)
        ->assertReturned(false)
        ->assertDispatched('notify');
});

test('saving without changing anything does not report a success', function (): void {
    $modality = ServiceModality::factory()->create(['code' => 'cita', 'name' => 'Cita']);

    Livewire::test('catalog.service-modality')
        ->call('openEdit', $modality->id)
        ->call('update')
        ->assertReturned(false);
});
