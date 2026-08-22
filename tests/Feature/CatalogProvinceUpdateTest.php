<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $province = Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $country->id]);

    $component = Livewire::test('catalog.province')->call('openEdit', $province->id);

    expect($component->get('form.recordId'))->toBe($province->id)
        ->and($component->get('form.data')->name)->toBe('Buenos Aires')
        ->and($component->get('form.data')->country_id)->toBe($country->id);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->set('form.data.name', 'Gran Buenos Aires')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(Province::query()->count())->toBe(1)
        ->and($province->fresh()->name)->toBe('Gran Buenos Aires');
});

test('keeping its own name while editing does not trip the scoped unique rule', function (): void {
    // The scoped unique would reject the record against itself; the update path
    // has to ignore its own id.
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($province->fresh()->is_active)->toBeFalse();
});

test('taking a name that already belongs to another province of the same country is rejected', function (): void {
    $venezuela = Country::factory()->create(['code' => 'VEN', 'name' => 'Venezuela']);
    Province::factory()->create(['name' => 'Mérida', 'country_id' => $venezuela->id]);
    $province = Province::factory()->create(['name' => 'Zulia', 'country_id' => $venezuela->id]);

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->set('form.data.name', 'Mérida')
        ->call('update')
        ->assertHasErrors('name')
        ->assertReturned(false);

    expect($province->fresh()->name)->toBe('Zulia');
});

test('moving a province to another country that already has that name is rejected', function (): void {
    // Mérida is legitimate in Venezuela, Mexico and Spain at the same time, but
    // one country cannot hold two. The unique is scoped by country, so the clash
    // only appears once the record lands in the other country — the rule has to
    // read the country being SAVED, not the one the record had.
    $venezuela = Country::factory()->create(['code' => 'VEN', 'name' => 'Venezuela']);
    $mexico = Country::factory()->create(['code' => 'MEX', 'name' => 'México']);
    Province::factory()->create(['name' => 'Mérida', 'country_id' => $mexico->id]);
    $province = Province::factory()->create(['name' => 'Mérida', 'country_id' => $venezuela->id]);

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->set('form.data.country_id', $mexico->id)
        ->call('update')
        ->assertHasErrors('name');

    expect($province->fresh()->country_id)->toBe($venezuela->id);
});

test('the country id posted by the combobox as a string is stored as the right integer', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    $other = Country::factory()->create(['code' => 'ESP', 'name' => 'España']);

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        // A real combobox posts the id as a string, never as an int.
        ->set('form.data.country_id', (string) $other->id)
        ->call('update')
        ->assertHasNoErrors();

    expect($province->fresh()->country_id)->toBe($other->id);
});

test('clearing the country reports a validation error instead of killing the component', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    $originalCountryId = $province->country_id;

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->set('form.data.country_id', '')
        ->call('update')
        ->assertHasErrors('country_id');

    expect($province->fresh()->country_id)->toBe($originalCountryId);
});

test('starting a new province clears the record left over from an edit', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    $component = Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->call('openCreate');

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->name)->toBe('')
        ->and($component->get('form.data')->country_id)->toBeNull();
});

test('opening a province that no longer exists warns instead of blowing up', function (): void {
    Livewire::test('catalog.province')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    Livewire::test('catalog.province')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the feminine', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    Livewire::test('catalog.province')
        ->call('openEdit', $province->id)
        ->set('form.data.name', 'Gran Buenos Aires')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Provincia actualizada correctamente');
});
