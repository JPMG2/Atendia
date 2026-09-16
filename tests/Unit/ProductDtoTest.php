<?php

declare(strict_types=1);

use App\Dto\ProductDto;

test('a fresh dto carries the product defaults', function (): void {
    $dto = new ProductDto;

    expect($dto->name)->toBe('')
        ->and($dto->code)->toBeNull()
        ->and($dto->description)->toBeNull()
        ->and($dto->price)->toBeNull()
        ->and($dto->stock)->toBeNull()
        ->and($dto->in_stock)->toBeTrue()
        ->and($dto->is_active)->toBeTrue();
});

test('the dto never carries the tenant, so a request cannot move a product to another business', function (): void {
    $dto = ProductDto::fromArray(['business_id' => 9, 'name' => 'Alternador']);

    expect($dto->toArray())->not->toHaveKey('business_id')
        ->and($dto->toPayload())->not->toHaveKey('business_id')
        ->and(property_exists($dto, 'business_id'))->toBeFalse();
});

test('emptied optional fields are stored as null, because the columns are nullable', function (): void {
    $dto = ProductDto::fromArray(['code' => '', 'price' => '', 'stock' => '', 'description' => '']);

    expect($dto->code)->toBeNull()
        ->and($dto->price)->toBeNull()
        ->and($dto->stock)->toBeNull()
        ->and($dto->description)->toBeNull();
});

test('the payload squishes hand-typed text and keeps amounts as strings', function (): void {
    // Strings on purpose: the columns are decimal and a float loses cents.
    $dto = new ProductDto(name: '  Bujía   NGK ', code: ' NGK-6ES ', price: '5200.50');

    expect($dto->toPayload())->toMatchArray([
        'name' => 'Bujía NGK',
        'code' => 'NGK-6ES',
        'price' => '5200.50',
    ]);
});

test('the livewire round-trip preserves the dto', function (): void {
    $dto = new ProductDto(name: 'Alternador', code: 'ALT-021', price: '185000.00', stock: '3', in_stock: false);

    $rehydrated = ProductDto::fromLivewire($dto->toLivewire());

    expect($rehydrated->toArray())->toBe($dto->toArray())
        ->and(ProductDto::fromLivewire(null)->name)->toBe('');
});
