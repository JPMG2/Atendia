<?php

declare(strict_types=1);

use App\Dto\BusinessDto;

test('a fresh dto carries the business defaults', function (): void {
    $dto = new BusinessDto;

    expect($dto->name)->toBe('')
        ->and($dto->country_id)->toBeNull()
        ->and($dto->province_id)->toBeNull()
        ->and($dto->timezone)->toBeNull()
        ->and($dto->billing_email)->toBe('')
        ->and($dto->whatsapp_number)->toBeNull()
        ->and($dto->fallback_whatsapp_number)->toBeNull()
        ->and($dto->is_active)->toBeTrue()
        ->and($dto->sector)->toBeNull();
});

test('the dto never carries an id, so the record identity cannot be rewritten from the browser', function (): void {
    $dto = BusinessDto::fromArray(['id' => 7, 'name' => 'La Esquina']);

    expect($dto->toArray())->not->toHaveKey('id')
        ->and(property_exists($dto, 'id'))->toBeFalse()
        ->and($dto->name)->toBe('La Esquina');
});

test('the ids arrive from the comboboxes as strings and are cast, instead of killing the component', function (): void {
    $dto = BusinessDto::fromArray(['country_id' => '3', 'province_id' => '12']);

    expect($dto->country_id)->toBe(3)
        ->and($dto->province_id)->toBe(12);
});

test('the empty option of a combobox is stored as null, never as a zero id', function (): void {
    $dto = BusinessDto::fromArray(['country_id' => '', 'province_id' => '']);

    expect($dto->country_id)->toBeNull()
        ->and($dto->province_id)->toBeNull();
});

test('the sector is screen state and stays out of the payload', function (): void {
    // It is not a column: it feeds the wizard suggestions and the activity
    // sync. Sending it to update() would throw on a strict model.
    $dto = BusinessDto::fromArray(['name' => 'La Esquina', 'sector' => 'gastronomia']);

    expect($dto->toArray())->toHaveKey('sector')
        ->and($dto->toPayload())->not->toHaveKey('sector');
});

test('the payload squishes hand-typed text and empties nullable columns to null', function (): void {
    $dto = new BusinessDto(
        name: '  La   Esquina ',
        billing_email: ' facturas@esquina.com ',
        whatsapp_number: '',
        fallback_whatsapp_number: '  ',
    );

    expect($dto->toPayload())->toMatchArray([
        'name' => 'La Esquina',
        'billing_email' => 'facturas@esquina.com',
        'whatsapp_number' => null,
        'fallback_whatsapp_number' => null,
    ]);
});

test('the livewire round-trip preserves the dto', function (): void {
    $dto = new BusinessDto(name: 'La Esquina', country_id: 2, sector: 'salud', whatsapp_number: '+54 9 11 5555-5555');

    $rehydrated = BusinessDto::fromLivewire($dto->toLivewire());

    expect($rehydrated->toArray())->toBe($dto->toArray())
        ->and($dto->toLivewire())->toBe($dto->toArray());
});

test('fromLivewire tolerates a non-array value by returning defaults', function (): void {
    $dto = BusinessDto::fromLivewire(null);

    expect($dto->name)->toBe('')
        ->and($dto->country_id)->toBeNull()
        ->and($dto->is_active)->toBeTrue();
});
