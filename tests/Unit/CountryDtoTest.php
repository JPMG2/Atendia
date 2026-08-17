<?php

declare(strict_types=1);

use App\Dto\CountryDto;
use App\Models\Country;

test('a fresh dto carries the maestro defaults', function (): void {
    $dto = new CountryDto;

    expect($dto->currency_id)->toBeNull()
        ->and($dto->name)->toBe('')
        ->and($dto->code)->toBe('')
        ->and($dto->phone_code)->toBeNull()
        ->and($dto->is_active)->toBeTrue();
});

test('the dto never carries an id, so the record identity cannot be rewritten from the browser', function (): void {
    // The id lives in CountryForm::$countryId, which is #[Locked]. An id on this
    // DTO would be a second, client-writable copy of the same identity — exactly
    // what the lock exists to prevent.
    $dto = CountryDto::fromArray(['id' => 7, 'code' => 'ARG']);

    expect($dto->toArray())->not->toHaveKey('id')
        ->and(property_exists($dto, 'id'))->toBeFalse()
        ->and($dto->code)->toBe('ARG');
});

test('toArray exposes every field', function (): void {
    $dto = new CountryDto(
        currency_id: 3,
        name: 'Argentina',
        code: 'ARG',
        phone_code: '54',
        is_active: false,
    );

    expect($dto->toArray())->toBe([
        'currency_id' => 3,
        'name' => 'Argentina',
        'code' => 'ARG',
        'phone_code' => '54',
        'is_active' => false,
    ]);
});

test('fromArray rebuilds the dto from a full payload', function (): void {
    $dto = CountryDto::fromArray([
        'currency_id' => 5,
        'name' => 'Uruguay',
        'code' => 'URY',
        'phone_code' => '598',
        'is_active' => true,
    ]);

    expect($dto->currency_id)->toBe(5)
        ->and($dto->name)->toBe('Uruguay')
        ->and($dto->code)->toBe('URY')
        ->and($dto->phone_code)->toBe('598');
});

test('fromArray falls back to defaults for missing keys', function (): void {
    $dto = CountryDto::fromArray(['code' => 'BRA']);

    expect($dto->code)->toBe('BRA')
        ->and($dto->name)->toBe('')
        ->and($dto->currency_id)->toBeNull()
        ->and($dto->phone_code)->toBeNull()
        ->and($dto->is_active)->toBeTrue();
});

test('the currency id arrives from the select as a string and is cast, instead of killing the component', function (): void {
    // A <select> posts "3", and CountryDto runs under strict_types: handing that
    // string straight to the `?int` parameter is a TypeError, which is an Error
    // and not an Exception, so tryAction would never catch it — Livewire answers
    // 419 and the whole editor disappears.
    expect(CountryDto::fromArray(['currency_id' => '3'])->currency_id)->toBe(3);
});

test('the empty option of the select is stored as null, never as a zero id', function (): void {
    // '' must not become 0: a 0 would sail past `required` and only fail on
    // `exists` as "the selected currency is invalid" — a confusing message for
    // a field the user simply left untouched.
    expect(CountryDto::fromArray(['currency_id' => ''])->currency_id)->toBeNull();
});

test('an emptied phone code is stored as null, because the column is nullable', function (): void {
    expect(CountryDto::fromArray(['phone_code' => ''])->phone_code)->toBeNull()
        ->and(CountryDto::fromArray(['phone_code' => '54'])->phone_code)->toBe('54');
});

test('the livewire round-trip preserves the dto', function (): void {
    $dto = new CountryDto(currency_id: 2, name: 'Chile', code: 'CHL', phone_code: '56', is_active: true);

    // toLivewire is what Livewire persists between requests; fromLivewire rehydrates it.
    $rehydrated = CountryDto::fromLivewire($dto->toLivewire());

    expect($rehydrated->toArray())->toBe($dto->toArray())
        ->and($dto->toLivewire())->toBe($dto->toArray());
});

test('fromLivewire tolerates a non-array value by returning defaults', function (): void {
    $dto = CountryDto::fromLivewire(null);

    expect($dto->code)->toBe('')
        ->and($dto->currency_id)->toBeNull()
        ->and($dto->is_active)->toBeTrue();
});

test('the dto normalizes through the model, so validation and storage can never diverge', function (): void {
    // Two separate copies of the normalization would mean validating one shape and
    // storing another the day one of them changes.
    $dto = new CountryDto(name: '  República   Dominicana ', code: 'dom', phone_code: ' 1 809 ');

    expect($dto->toPayload())->toMatchArray([
        'name' => Country::normalizeName('  República   Dominicana '),
        'code' => Country::normalizeCode('dom'),
        'phone_code' => Country::normalizePhoneCode(' 1 809 '),
    ]);
});
