<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\ServiceAttribute;
use App\Models\ServiceType;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| service_type_attribute — the attribute set
|--------------------------------------------------------------------------
*/

test('one attribute is reused by many types instead of being copied per type', function (): void {
    $notes = ServiceAttribute::factory()->create(['code' => 'notas', 'name' => 'Notas']);
    $consulta = ServiceType::factory()->create(['name' => 'Consulta']);
    $arreglo = ServiceType::factory()->create(['name' => 'Arreglo']);

    $consulta->serviceAttributes()->attach($notes, ['sort_order' => 1]);
    $arreglo->serviceAttributes()->attach($notes, ['sort_order' => 1]);

    // One row in the library, two types using it. That reuse is the point.
    expect(ServiceAttribute::query()->where('code', 'notas')->count())->toBe(1)
        ->and($notes->types()->count())->toBe(2);
});

test('the same attribute can be required in one type and optional in another', function (): void {
    $zone = ServiceAttribute::factory()->create(['code' => 'zona']);
    $presupuesto = ServiceType::factory()->create();
    $mesa = ServiceType::factory()->create();

    $presupuesto->serviceAttributes()->attach($zone, ['is_required' => true]);
    $mesa->serviceAttributes()->attach($zone, ['is_required' => false]);

    expect($presupuesto->serviceAttributes()->first()->pivot->is_required)->toBeTrue()
        ->and($mesa->serviceAttributes()->first()->pivot->is_required)->toBeFalse();
});

test('a type can rename an attribute for itself without touching the library', function (): void {
    // "Personas" globally, "Comensales" on a table. Without the override the
    // admin ends up creating three attributes for the same piece of data.
    $people = ServiceAttribute::factory()->create(['code' => 'personas', 'name' => 'Personas']);
    $mesa = ServiceType::factory()->create();

    $mesa->serviceAttributes()->attach($people, ['label_override' => 'Comensales']);

    expect($mesa->serviceAttributes()->first()->pivot->label_override)->toBe('Comensales')
        ->and($people->fresh()->name)->toBe('Personas');
});

test('an attribute cannot be attached twice to the same type', function (): void {
    $attribute = ServiceAttribute::factory()->create();
    $type = ServiceType::factory()->create();

    $type->serviceAttributes()->attach($attribute);

    expect(fn (): mixed => $type->serviceAttributes()->attach($attribute))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the attributes of a type come out in the order the admin set', function (): void {
    $type = ServiceType::factory()->create();
    $first = ServiceAttribute::factory()->create(['name' => 'Zeta']);
    $second = ServiceAttribute::factory()->create(['name' => 'Alfa']);

    $type->serviceAttributes()->attach($first, ['sort_order' => 1]);
    $type->serviceAttributes()->attach($second, ['sort_order' => 2]);

    expect($type->serviceAttributes()->pluck('name')->all())->toBe(['Zeta', 'Alfa']);
});

test('an attribute in use cannot change its data type, so stored values stay readable', function (): void {
    // Same rule Drupal and commercetools apply: the attribute level cannot be
    // changed after saving. Flipping "number" to "boolean" would break every
    // value already loaded by every business.
    $attribute = ServiceAttribute::factory()->create(['code' => 'cupo', 'data_type' => 'number']);
    ServiceType::factory()->create()->serviceAttributes()->attach($attribute);

    Livewire\Livewire::test('catalog.service-attribute')
        ->call('openEdit', $attribute->id)
        ->set('form.data.data_type', 'boolean')
        ->call('update')
        ->assertHasErrors('data_type');

    expect($attribute->fresh()->data_type)->toBe('number');
});

test('an attribute nobody uses can still change its data type', function (): void {
    $attribute = ServiceAttribute::factory()->create(['data_type' => 'number']);

    Livewire\Livewire::test('catalog.service-attribute')
        ->call('openEdit', $attribute->id)
        ->set('form.data.data_type', 'boolean')
        ->call('update')
        ->assertHasNoErrors();

    expect($attribute->fresh()->data_type)->toBe('boolean');
});

/*
|--------------------------------------------------------------------------
| activity_service_type — the suggestion, never a permission
|--------------------------------------------------------------------------
*/

test('a type is suggested to the activities that usually offer it', function (): void {
    seedServiceCatalog();

    $restaurant = BusinessActivity::query()->firstWhere('code', 'restaurante');

    expect($restaurant->suggestedServiceTypes->pluck('code')->all())
        ->toBe(['plato', 'combo', 'mesa', 'pedido-llevar']);
});

test('a bakery is not suggested a table, but nothing in the schema forbids adopting one', function (): void {
    // The case that shaped the whole design. The catalog suggests; it never
    // prohibits. There is no constraint tying a business to its suggestions.
    seedServiceCatalog();

    $bakery = BusinessActivity::query()->firstWhere('code', 'panaderia');
    $table = ServiceType::query()->firstWhere('code', 'mesa');

    expect($bakery->suggestedServiceTypes->pluck('code')->all())
        ->not->toContain('mesa');

    // Nothing stops it: adding the suggestion is a plain insert, no exception.
    $bakery->suggestedServiceTypes()->attach($table, ['sort_order' => 9]);

    expect($bakery->fresh()->suggestedServiceTypes->pluck('code')->all())->toContain('mesa');
});

test('one type is suggested to activities across different sectors', function (): void {
    seedServiceCatalog();

    // "Consulta" serves a doctor, a dentist and a vet: the type is global and the
    // sector column only groups the admin screen.
    $consulta = ServiceType::query()->firstWhere('code', 'consulta');

    expect($consulta->activities->pluck('code')->all())
        ->toContain('consultorio-medico', 'odontologia', 'veterinaria');
});

test('seeding twice does not duplicate the pivots', function (): void {
    seedServiceCatalog();
    $before = [DB::table('service_type_attribute')->count(), DB::table('activity_service_type')->count()];

    (new ServiceTypeSeeder)->run();

    expect([DB::table('service_type_attribute')->count(), DB::table('activity_service_type')->count()])
        ->toBe($before);
});

function seedServiceCatalog(): void
{
    foreach ([BusinessSectorSeeder::class, BusinessActivitySeeder::class, ServiceModalitySeeder::class, ServiceAttributeSeeder::class, ServiceTypeSeeder::class] as $seeder) {
        (new $seeder)->run();
    }
}
