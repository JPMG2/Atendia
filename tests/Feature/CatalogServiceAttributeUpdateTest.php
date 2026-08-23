<?php

declare(strict_types=1);

use App\Models\ServiceAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $attribute = ServiceAttribute::factory()->create([
        'code' => 'duracion',
        'name' => 'Duración',
        'description' => 'Cuánto lleva.',
        'data_type' => 'number',
        'unit' => 'min',
        'sort_order' => 2,
    ]);

    $component = Livewire::test('catalog.service-attribute')->call('openEdit', $attribute->id);

    expect($component->get('form.recordId'))->toBe($attribute->id)
        ->and($component->get('form.data')->code)->toBe('duracion')
        ->and($component->get('form.data')->name)->toBe('Duración')
        ->and($component->get('form.data')->data_type)->toBe('number')
        ->and($component->get('form.data')->unit)->toBe('min')
        ->and($component->get('form.data')->sort_order)->toBe(2);
});

test('editing a record updates it instead of creating a second one', function (): void {
    $attribute = ServiceAttribute::factory()->create(['code' => 'duracion', 'name' => 'Duracion']);

    Livewire::test('catalog.service-attribute')
        ->call('openEdit', $attribute->id)
        ->set('form.data.name', 'Duración')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(ServiceAttribute::query()->count())->toBe(1)
        ->and($attribute->fresh()->name)->toBe('Duración');
});

test('changing the type away from list clears the options it can no longer use', function (): void {
    $attribute = ServiceAttribute::factory()->list()->create(['code' => 'talle', 'name' => 'Talle']);

    Livewire::test('catalog.service-attribute')
        ->call('openEdit', $attribute->id)
        ->set('form.data.data_type', 'text')
        ->call('update')
        ->assertHasNoErrors();

    expect($attribute->fresh()->options)->toBeNull();
});

test('keeping its own code and name while editing does not trip the unique rules', function (): void {
    $attribute = ServiceAttribute::factory()->create(['code' => 'precio', 'name' => 'Precio']);

    Livewire::test('catalog.service-attribute')
        ->call('openEdit', $attribute->id)
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($attribute->fresh()->is_active)->toBeFalse();
});

test('taking a code that already belongs to another attribute is rejected', function (): void {
    ServiceAttribute::factory()->create(['code' => 'precio', 'name' => 'Precio']);
    $attribute = ServiceAttribute::factory()->create(['code' => 'duracion', 'name' => 'Duración']);

    Livewire::test('catalog.service-attribute')
        ->call('openEdit', $attribute->id)
        ->set('form.data.code', 'precio')
        ->call('update')
        ->assertHasErrors('code');

    expect($attribute->fresh()->code)->toBe('duracion');
});

test('opening a record that no longer exists leaves the user on the list', function (): void {
    Livewire::test('catalog.service-attribute')
        ->call('openEdit', 9999)
        ->assertReturned(false)
        ->assertDispatched('notify');
});
