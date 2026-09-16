<?php

declare(strict_types=1);

use App\Dto\ServiceDto;

test('a fresh dto carries the service defaults', function (): void {
    $dto = new ServiceDto;

    expect($dto->service_type_id)->toBeNull()
        ->and($dto->service_category_id)->toBeNull()
        ->and($dto->name)->toBe('')
        ->and($dto->description)->toBeNull()
        ->and($dto->prep_note)->toBeNull()
        ->and($dto->price_type)->toBe('fixed')
        ->and($dto->price)->toBeNull()
        ->and($dto->deposit)->toBeNull()
        ->and($dto->duration_minutes)->toBeNull()
        ->and($dto->is_active)->toBeTrue()
        ->and($dto->is_featured)->toBeFalse();
});

test('the dto never carries the tenant, so a request cannot move a service to another business', function (): void {
    // Mirrors the model boundary: business_id is not fillable and rows are
    // created through $business->services()->create().
    $dto = ServiceDto::fromArray(['business_id' => 9, 'name' => 'Ecodoppler']);

    expect($dto->toArray())->not->toHaveKey('business_id')
        ->and($dto->toPayload())->not->toHaveKey('business_id')
        ->and(property_exists($dto, 'business_id'))->toBeFalse();
});

test('numbers arrive from the inputs as strings and are cast, instead of killing the component', function (): void {
    $dto = ServiceDto::fromArray(['service_type_id' => '4', 'duration_minutes' => '45']);

    expect($dto->service_type_id)->toBe(4)
        ->and($dto->duration_minutes)->toBe(45);
});

test('emptied optional fields are stored as null, because the columns are nullable', function (): void {
    $dto = ServiceDto::fromArray(['price' => '', 'duration_minutes' => '', 'description' => '']);

    expect($dto->price)->toBeNull()
        ->and($dto->duration_minutes)->toBeNull()
        ->and($dto->description)->toBeNull();
});

test('the price stays a string so cents survive the trip, matching the decimal cast', function (): void {
    expect(ServiceDto::fromArray(['price' => '1500.50'])->price)->toBe('1500.50')
        ->and(ServiceDto::fromArray(['price' => '1500.50'])->toPayload()['price'])->toBe('1500.50');
});

test('the payload squishes hand-typed text and empties nullable columns to null', function (): void {
    $dto = new ServiceDto(name: '  Corte   de pelo ', description: '  ');

    expect($dto->toPayload())->toMatchArray([
        'name' => 'Corte de pelo',
        'description' => null,
    ]);
});

test('a free or to-be-agreed type drops the amount from the payload', function (): void {
    // Keeping an amount the type denies would make the assistant quote it.
    expect((new ServiceDto(price_type: 'free', price: '9000'))->toPayload()['price'])->toBeNull()
        ->and((new ServiceDto(price_type: 'talk', price: '9000'))->toPayload()['price'])->toBeNull()
        ->and((new ServiceDto(price_type: 'from', price: '9000'))->toPayload()['price'])->toBe('9000')
        // The deposit is independent: an unpriced booking still takes one.
        ->and((new ServiceDto(price_type: 'talk', deposit: '5000'))->toPayload()['deposit'])->toBe('5000');
});

test('the livewire round-trip preserves the dto', function (): void {
    $dto = new ServiceDto(service_type_id: 2, name: 'Dobladillo', price: '900.00', duration_minutes: 30);

    $rehydrated = ServiceDto::fromLivewire($dto->toLivewire());

    expect($rehydrated->toArray())->toBe($dto->toArray())
        ->and($dto->toLivewire())->toBe($dto->toArray());
});

test('fromLivewire tolerates a non-array value by returning defaults', function (): void {
    $dto = ServiceDto::fromLivewire(null);

    expect($dto->name)->toBe('')
        ->and($dto->service_type_id)->toBeNull()
        ->and($dto->is_active)->toBeTrue();
});
