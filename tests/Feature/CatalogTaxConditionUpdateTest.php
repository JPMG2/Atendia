<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\TaxCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $condition = TaxCondition::factory()->create([
        'code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $country->id, 'discriminate_tax' => true,
    ]);

    $component = Livewire::test('catalog.tax-condition')->call('openEdit', $condition->id);

    expect($component->get('form.recordId'))->toBe($condition->id)
        ->and($component->get('form.data')->code)->toBe('RI')
        ->and($component->get('form.data')->name)->toBe('Responsable Inscripto')
        ->and($component->get('form.data')->country_id)->toBe($country->id)
        ->and($component->get('form.data')->discriminate_tax)->toBeTrue();
});

test('editing a record updates it instead of creating a second one', function (): void {
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto']);

    Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->set('form.data.name', 'Resp. Inscripto')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(TaxCondition::query()->count())->toBe(1)
        ->and($condition->fresh()->name)->toBe('Resp. Inscripto');
});

test('turning the discrimination flag off is saved', function (): void {
    // A boolean that never reaches the validated payload silently stays as it
    // was; this is the test that catches a missing rule for `discriminate_tax`.
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'discriminate_tax' => true]);

    Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->set('form.data.discriminate_tax', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($condition->fresh()->discriminate_tax)->toBeFalse();
});

test('keeping its own code while editing does not trip the scoped unique rule', function (): void {
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto']);

    Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($condition->fresh()->is_active)->toBeFalse();
});

test('taking a code that already belongs to another condition of the same country is rejected', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    TaxCondition::factory()->create(['code' => 'EX', 'name' => 'IVA Exento', 'country_id' => $country->id]);
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $country->id]);

    Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->set('form.data.code', 'EX')
        ->call('update')
        ->assertHasErrors('code')
        ->assertReturned(false);

    expect($condition->fresh()->code)->toBe('RI');
});

test('moving a condition to a country that already has that code is rejected', function (): void {
    // The unique is scoped by country, so the clash only appears once the record
    // lands in the other country.
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $uruguay = Country::factory()->create(['code' => 'URY', 'name' => 'Uruguay']);
    TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Otro', 'country_id' => $uruguay->id]);
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $argentina->id]);

    Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->set('form.data.country_id', $uruguay->id)
        ->call('update')
        ->assertHasErrors('code');

    expect($condition->fresh()->country_id)->toBe($argentina->id);
});

test('starting a new condition clears the record left over from an edit', function (): void {
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'discriminate_tax' => true]);

    $component = Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->call('openCreate');

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->code)->toBe('')
        ->and($component->get('form.data')->name)->toBe('')
        ->and($component->get('form.data')->country_id)->toBeNull()
        // The flag has to reset too, or the new record inherits the previous tick.
        ->and($component->get('form.data')->discriminate_tax)->toBeFalse();
});

test('opening a condition that no longer exists warns instead of blowing up', function (): void {
    Livewire::test('catalog.tax-condition')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    Livewire::test('catalog.tax-condition')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the feminine', function (): void {
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto']);

    Livewire::test('catalog.tax-condition')
        ->call('openEdit', $condition->id)
        ->set('form.data.name', 'Resp. Inscripto')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Condición fiscal actualizada correctamente');
});
