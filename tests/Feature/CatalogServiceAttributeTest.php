<?php

declare(strict_types=1);

use App\Models\ServiceAttribute;
use Database\Seeders\ServiceAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.service-attribute')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Duración')
        ->assertSet('form.data.name', 'Duración');
});

test('the attribute table hands its rows to Alpine so the search filters client-side', function (): void {
    $attribute = ServiceAttribute::factory()->create(['name' => 'Duración']);

    $html = Livewire::test('catalog.service-attribute')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($attribute->name);
});

test('every row carries its id, because the name is user-editable and cannot identify a row', function (): void {
    $attribute = ServiceAttribute::factory()->create();

    $html = Livewire::test('catalog.service-attribute')->html();

    expect($html)->toContain(':key="row.id"');

    expect(railConfig($html, 'items'))->toHaveCount(1)
        ->and(railConfig($html, 'items')[0]['id'])->toBe($attribute->id);
});

test('the attribute editor renders its real inputs', function (): void {
    Livewire::test('catalog.service-attribute')
        ->assertSee('Clave')
        ->assertSee('Nombre')
        ->assertSee('Tipo de dato')
        ->assertSee('Unidad')
        ->assertSee('Opciones de la lista')
        ->assertSee('Orden')
        ->assertSee('Estado');
});

test('a new attribute starts as plain text, the safest type', function (): void {
    $component = Livewire::test('catalog.service-attribute');

    expect($component->get('form.data')->data_type)->toBe('text')
        ->and($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->options)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| store — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('an attribute is created with all of its attributes', function (): void {
    Livewire::test('catalog.service-attribute')
        ->set('form.data.code', 'duracion')
        ->set('form.data.name', 'Duración')
        ->set('form.data.description', 'Cuánto lleva la atención.')
        ->set('form.data.data_type', 'number')
        ->set('form.data.unit', 'min')
        ->set('form.data.sort_order', 2)
        ->call('create')
        ->assertHasNoErrors();

    $attribute = ServiceAttribute::query()->firstWhere('code', 'duracion');

    expect($attribute)->not->toBeNull()
        ->and($attribute->name)->toBe('Duración')
        ->and($attribute->data_type)->toBe('number')
        ->and($attribute->unit)->toBe('min')
        ->and($attribute->sort_order)->toBe(2);
});

test('the options are typed as one comma separated line and stored as a list', function (): void {
    // The admin writes "Chico, Mediano, Grande" in a single field; the column is
    // jsonb, so the conversion happens once, in the DTO.
    Livewire::test('catalog.service-attribute')
        ->set('form.data.code', 'talle')
        ->set('form.data.name', 'Talle')
        ->set('form.data.data_type', 'list')
        ->set('form.data.options', 'Chico,  Mediano , Grande')
        ->call('create')
        ->assertHasNoErrors();

    expect(ServiceAttribute::query()->firstWhere('code', 'talle')->options)
        ->toBe(['Chico', 'Mediano', 'Grande']);
});

test('an empty option is dropped instead of reaching the list the business sees', function (): void {
    Livewire::test('catalog.service-attribute')
        ->set('form.data.code', 'talle')
        ->set('form.data.name', 'Talle')
        ->set('form.data.data_type', 'list')
        ->set('form.data.options', 'Chico, , Grande,')
        ->call('create')
        ->assertHasNoErrors();

    expect(ServiceAttribute::query()->firstWhere('code', 'talle')->options)
        ->toBe(['Chico', 'Grande']);
});

test('options are dropped when the type is not a list, so no orphan list is stored', function (): void {
    Livewire::test('catalog.service-attribute')
        ->set('form.data.code', 'precio')
        ->set('form.data.name', 'Precio')
        ->set('form.data.data_type', 'money')
        ->set('form.data.options', 'Chico, Grande')
        ->call('create')
        ->assertHasNoErrors();

    expect(ServiceAttribute::query()->firstWhere('code', 'precio')->options)->toBeNull();
});

test('a stored list comes back into the form as the same comma separated line', function (): void {
    $attribute = ServiceAttribute::factory()->list()->create(['code' => 'talle', 'name' => 'Talle']);

    $component = Livewire::test('catalog.service-attribute')->call('openEdit', $attribute->id);

    expect($component->get('form.data')->options)->toBe('Chico, Mediano, Grande');
});

test('the data type has to be one the system knows how to draw', function (): void {
    Livewire::test('catalog.service-attribute')
        ->set('form.data.code', 'raro')
        ->set('form.data.name', 'Raro')
        ->set('form.data.data_type', 'inventado')
        ->call('create')
        ->assertHasErrors('data_type');
});

test('two attributes cannot share a code, because the code is what the pivot references', function (): void {
    ServiceAttribute::factory()->create(['code' => 'precio']);

    Livewire::test('catalog.service-attribute')
        ->set('form.data.code', 'precio')
        ->set('form.data.name', 'Otro precio')
        ->call('create')
        ->assertHasErrors('code');

    expect(ServiceAttribute::query()->where('code', 'precio')->count())->toBe(1);
});

test('the row shows the readable type label, not the raw key', function (): void {
    ServiceAttribute::factory()->create(['code' => 'talle', 'data_type' => 'list']);

    $rows = Livewire::test('catalog.service-attribute')->get('initialRows');

    expect($rows[0]['type'])->toBe('Lista de opciones');
});

test('a data type that no longer exists falls back to text instead of showing nothing', function (): void {
    // A type could be removed from config after being used. The cell must still
    // say something, or the attribute looks like it has no type at all.
    ServiceAttribute::factory()->create(['data_type' => 'quitado-de-config']);

    $rows = Livewire::test('catalog.service-attribute')->get('initialRows');

    expect($rows[0]['type'])->toBe('Texto');
});

test('the seeded library is shared across trades, not copied per vertical', function (): void {
    $this->seed(ServiceAttributeSeeder::class);

    // `foto` is one row that a restaurant dish and a clothing item both use.
    // That reuse is the whole point of the library.
    expect(ServiceAttribute::query()->where('code', 'foto')->count())->toBe(1)
        ->and(ServiceAttribute::query()->count())->toBeGreaterThan(20);
});

test('price, stock and duration are not in the library, they are first-class fields', function (): void {
    // They are queried, sorted, filtered and bulk-updated all the time, and they
    // need currency, transactions and a calendar. Inside a generic jsonb blob
    // none of that can be resolved, so they live as columns of what the business
    // adopts. Same reason commercetools puts them on the variant.
    $this->seed(ServiceAttributeSeeder::class);

    expect(ServiceAttribute::query()->whereIn('code', ['precio', 'stock', 'duracion'])->count())->toBe(0);
});

test('an attribute that means several things at once is marked as multiple', function (): void {
    // "Obra social" is not one: a practice takes OSDE, Swiss Medical and Galeno.
    // Without cardinality the business crams them into a single string and the
    // assistant cannot filter by coverage.
    $this->seed(ServiceAttributeSeeder::class);

    expect(ServiceAttribute::query()->firstWhere('code', 'obra_social')->is_multiple)->toBeTrue()
        ->and(ServiceAttribute::query()->firstWhere('code', 'apto_celiaco')->is_multiple)->toBeFalse();
});

test('seeding twice does not duplicate an attribute', function (): void {
    $this->seed(ServiceAttributeSeeder::class);
    $this->seed(ServiceAttributeSeeder::class);

    expect(ServiceAttribute::query()->where('code', 'obra_social')->count())->toBe(1);
});
