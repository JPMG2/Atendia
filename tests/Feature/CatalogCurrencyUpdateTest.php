<?php

declare(strict_types=1);

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $currency = Currency::factory()->create([
        'code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$', 'decimal_places' => 2,
    ]);

    $component = Livewire::test('catalog.currency')->call('openEdit', $currency->id);

    expect($component->get('form.recordId'))->toBe($currency->id)
        ->and($component->get('form.data')->code)->toBe('ARS')
        ->and($component->get('form.data')->name)->toBe($currency->name)
        ->and($component->get('form.data')->symbol)->toBe('$')
        ->and($component->get('form.data')->decimal_places)->toBe(2);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->set('form.data.name', 'Peso rioplatense')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(Currency::query()->count())->toBe(1)
        ->and($currency->fresh()->name)->toBe('Peso rioplatense');
});

test('keeping its own code while editing does not trip the unique rule', function (): void {
    // `unique:currencies,code` would reject the record against itself; the update
    // path has to ignore its own id.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->set('form.data.symbol', 'AR$')
        ->call('update')
        ->assertHasNoErrors();

    expect($currency->fresh()->symbol)->toBe('AR$');
});

test('taking a code that already belongs to another currency is rejected', function (): void {
    Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->set('form.data.code', 'USD')
        ->call('update')
        ->assertHasErrors('code')
        ->assertReturned(false);

    expect($currency->fresh()->code)->toBe('ARS');
});

test('clearing the decimals field reports a validation error instead of killing the component', function (): void {
    // `decimal_places` used to be a non-nullable `int` on the DTO, so emptying the
    // number input threw a TypeError, Livewire answered 419 and the whole editor
    // vanished — no message, nothing saved.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino', 'decimal_places' => 2]);

    Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->set('form.data.decimal_places', null)
        ->call('update')
        ->assertHasErrors('decimal_places');

    expect($currency->fresh()->decimal_places)->toBe(2);
});

test('an emptied decimals field stays empty instead of silently showing a 2 the user never typed', function (): void {
    $component = Livewire::test('catalog.currency')
        ->set('form.data.decimal_places', null);

    expect($component->get('form.data')->decimal_places)->toBeNull();
});

test('starting a new currency clears the record left over from an edit', function (): void {
    // openCreate() on the Alpine side only reset the client; the server kept the
    // previous record, so "Nueva moneda" opened pre-filled with someone else's data.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $component = Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->call('openCreate');

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->code)->toBe('')
        ->and($component->get('form.data')->name)->toBe('')
        ->and($component->get('form.data')->symbol)->toBe('')
        ->and($component->get('form.data')->decimal_places)->toBe(2);
});

test('a save that fails reports it back so the front keeps the user on the form', function (): void {
    Livewire::test('catalog.currency')
        ->set('form.data.code', 'ARS')
        ->set('form.data.name', 'Peso argentino')
        ->set('form.data.symbol', '$')
        ->set('form.data.decimal_places', 2)
        ->call('create')
        ->assertReturned(true);
});

test('opening a currency that no longer exists warns instead of blowing up', function (): void {
    // findOrFail answered a raw 404 in the middle of the panel. It is not only a
    // crafted `$wire.openEdit(999999)`: another user deleting the row while this
    // list sits open on screen gets there too.
    Livewire::test('catalog.currency')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('a failed open leaves the form untouched instead of half-loading a record', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $component = Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->call('openEdit', 999999);

    // The currency that WAS open must survive: recordId cannot end up pointing at
    // a row that does not exist, or the next save would target nothing.
    expect($component->get('form.recordId'))->toBe($currency->id)
        ->and($component->get('form.data')->code)->toBe('ARS');
});

test('a symbol longer than the form allows is rejected by validation, not by Postgres', function (): void {
    // The column is varchar(10) and the input caps at 5, but the rule said max:255:
    // anything in between sailed past validation and blew up in the database.
    Livewire::test('catalog.currency')
        ->set('form.data.code', 'ZZZ')
        ->set('form.data.name', 'Moneda de prueba')
        ->set('form.data.symbol', 'ABCDEFGH')
        ->set('form.data.decimal_places', 2)
        ->call('create')
        ->assertHasErrors('symbol');

    expect(Currency::query()->where('code', 'ZZZ')->exists())->toBeFalse();
});

test('a duplicate code typed in lowercase is caught as a field error, not as a database crash', function (): void {
    // This is why toPayload() normalises BEFORE validation: `unique` would
    // compare the lowercase input against the stored uppercase code, find
    // nothing, and the clash would surface as a Postgres error.
    Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.currency')
        ->set('form.data.code', 'ars')
        ->set('form.data.name', 'Peso argentino bis')
        ->set('form.data.symbol', '$')
        ->set('form.data.decimal_places', 2)
        ->call('create')
        ->assertHasErrors('code');

    expect(Currency::query()->count())->toBe(1);
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    // $wire.update() is reachable from the console without opening any row. The
    // action now demands an int id, so an unguarded null would be a TypeError —
    // which is an Error, not an Exception, so tryAction would never catch it.
    Livewire::test('catalog.currency')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});
