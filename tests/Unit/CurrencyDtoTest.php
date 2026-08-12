<?php

declare(strict_types=1);

use App\Dto\CurrencyDto;
use App\Models\Currency;

test('a fresh dto carries the maestro defaults', function (): void {
    $dto = new CurrencyDto;

    expect($dto->code)->toBe('')
        ->and($dto->name)->toBe('')
        ->and($dto->symbol)->toBe('')
        ->and($dto->decimal_places)->toBe(2)
        ->and($dto->is_active)->toBeTrue();
});

test('the dto never carries an id, so the record identity cannot be rewritten from the browser', function (): void {
    // The id lives in CurrencyForm::$currencyId, which is #[Locked]. An id on this
    // DTO would be a second, client-writable copy of the same identity — exactly
    // what the lock exists to prevent.
    $dto = CurrencyDto::fromArray(['id' => 7, 'code' => 'ARS']);

    expect($dto->toArray())->not->toHaveKey('id')
        ->and(property_exists($dto, 'id'))->toBeFalse()
        ->and($dto->code)->toBe('ARS');
});

test('toArray exposes every field', function (): void {
    $dto = new CurrencyDto(
        code: 'ARS',
        name: 'Peso Argentino',
        symbol: '$',
        decimal_places: 4,
        is_active: false,
    );

    expect($dto->toArray())->toBe([
        'code' => 'ARS',
        'name' => 'Peso Argentino',
        'symbol' => '$',
        'decimal_places' => 4,
        'is_active' => false,
    ]);
});

test('fromArray rebuilds the dto from a full payload', function (): void {
    $dto = CurrencyDto::fromArray([
        'code' => 'USD',
        'name' => 'Dólar',
        'symbol' => 'US$',
        'decimal_places' => 2,
        'is_active' => true,
    ]);

    expect($dto->code)->toBe('USD')
        ->and($dto->name)->toBe('Dólar')
        ->and($dto->symbol)->toBe('US$');
});

test('fromArray falls back to defaults for missing keys', function (): void {
    $dto = CurrencyDto::fromArray(['code' => 'EUR']);

    expect($dto->code)->toBe('EUR')
        ->and($dto->name)->toBe('')
        ->and($dto->decimal_places)->toBe(2)
        ->and($dto->is_active)->toBeTrue();
});

test('fromArray keeps an explicitly emptied decimals instead of resurrecting the default', function (): void {
    // `??` would turn the field the user just cleared into a 2 they never typed,
    // shown next to the "is required" error. Absent => default; present null => null.
    expect(CurrencyDto::fromArray(['code' => 'EUR', 'decimal_places' => null])->decimal_places)->toBeNull()
        ->and(CurrencyDto::fromArray(['code' => 'EUR'])->decimal_places)->toBe(2);
});

test('the livewire round-trip preserves the dto', function (): void {
    $dto = new CurrencyDto(code: 'CLP', name: 'Peso Chileno', symbol: '$', decimal_places: 0, is_active: true);

    // toLivewire is what Livewire persists between requests; fromLivewire rehydrates it.
    $rehydrated = CurrencyDto::fromLivewire($dto->toLivewire());

    expect($rehydrated->toArray())->toBe($dto->toArray())
        ->and($dto->toLivewire())->toBe($dto->toArray());
});

test('fromLivewire tolerates a non-array value by returning defaults', function (): void {
    $dto = CurrencyDto::fromLivewire(null);

    expect($dto->code)->toBe('')
        ->and($dto->decimal_places)->toBe(2)
        ->and($dto->is_active)->toBeTrue();
});

test('the dto normalizes through the model, so validation and storage can never diverge', function (): void {
    // Two separate copies of the normalization would mean validating one shape and
    // storing another the day one of them changes.
    $dto = new CurrencyDto(code: 'ars', name: 'PESO ARGENTINO', symbol: 'ar$');

    expect($dto->toPayload())->toMatchArray([
        'code' => Currency::normalizeCode('ars'),
        'name' => Currency::normalizeName('PESO ARGENTINO'),
        'symbol' => Currency::normalizeSymbol('ar$'),
    ]);
});
