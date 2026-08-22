<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\BusinessActivityForm;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.business-activity')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Panadería')
        ->assertSet('form.data.name', 'Panadería');
});

test('the activity table hands its rows to Alpine so the search filters client-side', function (): void {
    $activity = BusinessActivity::factory()->create(['name' => 'Panadería']);

    $html = Livewire::test('catalog.business-activity')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($activity->name);
});

test('every row carries its sector name, so the table shows it without a query per row', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);
    BusinessActivity::factory()->for($sector, 'sector')->create(['name' => 'Panadería']);

    $rows = Livewire::test('catalog.business-activity')->get('initialRows');

    expect($rows[0]['sector'])->toBe('Gastronomía');
});

test('the activities are grouped by sector and ordered inside it, as the business will see them', function (): void {
    $first = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía', 'sort_order' => 1]);
    $second = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud', 'sort_order' => 2]);

    BusinessActivity::factory()->for($second, 'sector')->create(['code' => 'farmacia', 'name' => 'Farmacia', 'sort_order' => 1]);
    BusinessActivity::factory()->for($first, 'sector')->create(['code' => 'restaurante', 'name' => 'Restaurante', 'sort_order' => 2]);
    BusinessActivity::factory()->for($first, 'sector')->create(['code' => 'panaderia', 'name' => 'Panadería', 'sort_order' => 1]);

    $rows = Livewire::test('catalog.business-activity')->get('initialRows');

    expect(collect($rows)->pluck('name')->all())->toBe(['Panadería', 'Restaurante', 'Farmacia']);
});

test('the activity editor renders its real inputs', function (): void {
    Livewire::test('catalog.business-activity')
        ->assertSee('Clave')
        ->assertSee('Nombre')
        ->assertSee('Rubro')
        ->assertSee('Descripción')
        ->assertSee('Orden')
        ->assertSee('Estado');
});

test('the sector combobox lists every sector, so an activity on a disabled sector keeps it', function (): void {
    // Filtering by is_active here would blank the combobox when opening such an
    // activity, and saving would silently move it to another sector.
    $active = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);
    $disabled = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza', 'is_active' => false]);

    $options = Livewire::test('catalog.business-activity')->instance()->sectorOptions;

    expect(collect($options)->pluck('value')->all())->toEqualCanonicalizing([$active->id, $disabled->id]);
});

test('the activity editor seeds a new record as active with no sector chosen', function (): void {
    $component = Livewire::test('catalog.business-activity');

    expect(railConfig($component->html(), 'blank')['active'])->toBeTrue()
        ->and($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->business_sector_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| storeBusinessActivity — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('an activity is created under its sector', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $sector->id)
        ->set('form.data.code', 'panaderia')
        ->set('form.data.name', 'Panadería')
        ->set('form.data.description', 'Pan y facturas')
        ->call('create')
        ->assertHasNoErrors();

    $activity = BusinessActivity::where('code', 'panaderia')->sole();

    expect($activity->business_sector_id)->toBe($sector->id)
        ->and($activity->name)->toBe('Panadería')
        ->and($activity->description)->toBe('Pan y facturas');
});

test('an activity with no sector is rejected as a field error, not as a foreign key crash', function (): void {
    // The FK is `constrained()`: without the rule this would blow up inside
    // tryAction and surface as a vague toast instead of an error on the combobox.
    Livewire::test('catalog.business-activity')
        ->set('form.data.code', 'panaderia')
        ->set('form.data.name', 'Panadería')
        ->call('create')
        ->assertHasErrors('business_sector_id');

    expect(BusinessActivity::query()->count())->toBe(0);
});

test('a sector id that does not exist is rejected', function (): void {
    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', 999999)
        ->set('form.data.code', 'panaderia')
        ->set('form.data.name', 'Panadería')
        ->call('create')
        ->assertHasErrors('business_sector_id');

    expect(BusinessActivity::query()->count())->toBe(0);
});

test('the same activity name is rejected inside one sector', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza']);
    BusinessActivity::factory()->for($sector, 'sector')->create(['code' => 'estetica', 'name' => 'Estética']);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $sector->id)
        ->set('form.data.code', 'estetica-2')
        ->set('form.data.name', 'Estética')
        ->call('create')
        ->assertHasErrors('name');

    expect(BusinessActivity::query()->count())->toBe(1);
});

test('the same activity name IS accepted in another sector', function (): void {
    // This is why the unique rule is scoped by sector instead of being global:
    // "Estética" is a real activity of Belleza AND of Servicios. A global unique
    // would reject the second; no rule at all would let one sector hold two.
    $beauty = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza']);
    $services = BusinessSector::factory()->create(['code' => 'servicios', 'name' => 'Servicios']);
    BusinessActivity::factory()->for($beauty, 'sector')->create(['code' => 'estetica', 'name' => 'Estética']);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $services->id)
        ->set('form.data.code', 'estetica-servicios')
        ->set('form.data.name', 'Estética')
        ->call('create')
        ->assertHasNoErrors();

    expect(BusinessActivity::where('name', 'Estética')->count())->toBe(2);
});

test('a code already used by another sector is rejected, because it keys the assistant profile', function (): void {
    // Unlike the name, the code is unique across the whole table: it is what the
    // assistant profile and the seed knowledge for the trade will be looked up by.
    $beauty = BusinessSector::factory()->create(['code' => 'belleza', 'name' => 'Belleza']);
    $services = BusinessSector::factory()->create(['code' => 'servicios', 'name' => 'Servicios']);
    BusinessActivity::factory()->for($beauty, 'sector')->create(['code' => 'estetica', 'name' => 'Estética']);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $services->id)
        ->set('form.data.code', 'estetica')
        ->set('form.data.name', 'Estética corporal')
        ->call('create')
        ->assertHasErrors('code');

    expect(BusinessActivity::query()->count())->toBe(1);
});

test('the sector id posted by the combobox as a string is stored as the right integer', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);

    Livewire::test('catalog.business-activity')
        // A real combobox posts the id as a string, never as an int.
        ->set('form.data.business_sector_id', (string) $sector->id)
        ->set('form.data.code', 'panaderia')
        ->set('form.data.name', 'Panadería')
        ->call('create')
        ->assertHasNoErrors();

    expect(BusinessActivity::where('code', 'panaderia')->value('business_sector_id'))->toBe($sector->id);
});

test('a name with markup is rejected', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $sector->id)
        ->set('form.data.code', 'panaderia')
        ->set('form.data.name', '<script>alert(1)</script>')
        ->call('create')
        ->assertHasErrors('name');
});

test('every attribute the action persists carries a validation rule', function (): void {
    $form = new BusinessActivityForm(
        new class extends Component
        {
            public function render(): string
            {
                return '<div></div>';
            }
        },
        'form',
    );
    $form->setup();

    $payload = (new ReflectionMethod($form, 'transformServiceData'))->invoke($form);
    $rules = (new ReflectionMethod($form, 'getValidationRules'))->invoke($form, null);

    expect(array_keys($payload))->toEqualCanonicalizing(array_keys($rules));
});

test('creating an activity hands the refreshed rows back to Alpine', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);
    BusinessActivity::factory()->for($sector, 'sector')->create(['code' => 'panaderia', 'name' => 'Panadería', 'sort_order' => 1]);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $sector->id)
        ->set('form.data.code', 'restaurante')
        ->set('form.data.name', 'Restaurante')
        ->set('form.data.sort_order', 2)
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('name')
                ->all() === ['Panadería', 'Restaurante'],
        );
});

test('the create toast is announced in the feminine', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'gastronomia', 'name' => 'Gastronomía']);

    Livewire::test('catalog.business-activity')
        ->set('form.data.business_sector_id', $sector->id)
        ->set('form.data.code', 'panaderia')
        ->set('form.data.name', 'Panadería')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Actividad creada correctamente');
});
