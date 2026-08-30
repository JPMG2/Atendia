<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    $country = Country::factory()->create([
        'code' => 'ARG', 'name' => 'Argentina', 'phone_code' => '54', 'currency_id' => $currency->id,
    ]);

    $component = Livewire::test('catalog.country')->call('openEdit', $country->id);

    expect($component->get('form.recordId'))->toBe($country->id)
        ->and($component->get('form.data')->code)->toBe('ARG')
        ->and($component->get('form.data')->name)->toBe($country->name)
        ->and($component->get('form.data')->phone_code)->toBe('54')
        ->and($component->get('form.data')->currency_id)->toBe($currency->id);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->set('form.data.name', 'República Argentina')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(Country::query()->count())->toBe(1)
        ->and($country->fresh()->name)->toBe('República Argentina');
});

test('keeping its own code while editing does not trip the unique rule', function (): void {
    // `unique:countries,code` would reject the record against itself; the update
    // path has to ignore its own id.
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->set('form.data.phone_code', '540')
        ->call('update')
        ->assertHasNoErrors();

    expect($country->fresh()->phone_code)->toBe('540');
});

test('taking a code that already belongs to another country is rejected', function (): void {
    Country::factory()->create(['code' => 'BOL', 'name' => 'Bolivia']);
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->set('form.data.code', 'BOL')
        ->call('update')
        ->assertHasErrors('code')
        ->assertReturned(false);

    expect($country->fresh()->code)->toBe('ARG');
});

test('taking a name that already belongs to another country is rejected as a field error', function (): void {
    // `name` is UNIQUE in the table: without the rule this would be a Postgres
    // crash swallowed by tryAction instead of a message on the field.
    Country::factory()->create(['code' => 'BOL', 'name' => 'Bolivia']);
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->set('form.data.name', 'Bolivia')
        ->call('update')
        ->assertHasErrors('name')
        ->assertReturned(false);

    expect($country->fresh()->name)->toBe('Argentina');
});

test('keeping its own name while editing does not trip the unique rule either', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($country->fresh()->is_active)->toBeFalse();
});

test('clearing the currency reports a validation error instead of killing the component', function (): void {
    // The empty option of the select posts '', which the DTO turns into null.
    // Without the `required` rule that null would hit the NOT NULL foreign key.
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $originalCurrencyId = $country->currency_id;

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->set('form.data.currency_id', '')
        ->call('update')
        ->assertHasErrors('currency_id');

    expect($country->fresh()->currency_id)->toBe($originalCurrencyId);
});

test('the currency id posted by the select as a string is stored as the right integer', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $other = Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);

    Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        // A real <select> posts the id as a string, never as an int.
        ->set('form.data.currency_id', (string) $other->id)
        ->call('update')
        ->assertHasNoErrors();

    expect($country->fresh()->currency_id)->toBe($other->id);
});

test('starting a new country clears the record left over from an edit', function (): void {
    // openCreate() on the Alpine side only reset the client; the server kept the
    // previous record, so "Nuevo país" opened pre-filled with someone else's data.
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    $component = Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->call('openCreate');

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->code)->toBe('')
        ->and($component->get('form.data')->name)->toBe('')
        ->and($component->get('form.data')->phone_code)->toBeNull()
        ->and($component->get('form.data')->currency_id)->toBeNull();
});

test('a save that fails reports it back so the front keeps the user on the form', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->set('form.data.phone_code', '54')
        ->call('create')
        ->assertReturned(true);
});

test('opening a country that no longer exists warns instead of blowing up', function (): void {
    // findOrFail answered a raw 404 in the middle of the panel. It is not only a
    // crafted `$wire.openEdit(999999)`: another user deleting the row while this
    // list sits open on screen gets there too.
    Livewire::test('catalog.country')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('a failed open leaves the form untouched instead of half-loading a record', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    $component = Livewire::test('catalog.country')
        ->call('openEdit', $country->id)
        ->call('openEdit', 999999);

    // The country that WAS open must survive: recordId cannot end up pointing at
    // a row that does not exist, or the next save would target nothing.
    expect($component->get('form.recordId'))->toBe($country->id)
        ->and($component->get('form.data')->code)->toBe('ARG');
});

test('a duplicate code typed in lowercase is caught as a field error, not as a database crash', function (): void {
    // This is why toPayload() normalises BEFORE validation: `unique` would
    // compare the lowercase input against the stored uppercase code, find
    // nothing, and the clash would surface as a Postgres error.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $currency->id]);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'arg')
        ->set('form.data.name', 'Argentina bis')
        ->call('create')
        ->assertHasErrors('code');

    expect(Country::query()->count())->toBe(1);
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    // $wire.update() is reachable from the console without opening any row. The
    // action now demands an int id, so an unguarded null would be a TypeError —
    // which is an Error, not an Exception, so tryAction would never catch it.
    Livewire::test('catalog.country')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});
