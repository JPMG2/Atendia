<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\BusinessSectorForm;
use App\Models\BusinessSector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;

// RefreshDatabase is commented out globally in tests/Pest.php, so these tests would
// otherwise hit sectors left over from previous runs.
uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it
    // into a real BusinessSectorDto. Without that, a wire:model update throws
    // "Cannot assign array to property" because Livewire cannot recurse into null.
    Livewire::test('catalog.business-sector')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Gastronomía')
        ->assertSet('form.data.name', 'Gastronomía');
});

test('the sector table hands its rows to Alpine so the search filters client-side', function (): void {
    $sector = BusinessSector::factory()->create(['name' => 'Gastronomía']);

    $html = Livewire::test('catalog.business-sector')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($sector->name);
});

test('every row carries its id, because the name is user-editable and cannot identify a row', function (): void {
    $sector = BusinessSector::factory()->create(['name' => 'Gastronomía']);

    $html = Livewire::test('catalog.business-sector')->html();

    expect($html)->toContain(':key="row.id"');

    expect(railConfig($html, 'items'))->toHaveCount(1)
        ->and(railConfig($html, 'items')[0]['id'])->toBe($sector->id);
});

test('the sector editor renders its real inputs', function (): void {
    Livewire::test('catalog.business-sector')
        ->assertSee('Clave')
        ->assertSee('Nombre')
        ->assertSee('Descripción')
        ->assertSee('Orden')
        ->assertSee('Estado');
});

test('the sectors are listed in the order the admin chose, not alphabetically', function (): void {
    // `sort_order` is the whole point of the field: it is what the business will
    // see when picking. Sorting by name here would make the column a lie.
    BusinessSector::factory()->create(['name' => 'Alimentos', 'code' => 'alimentos', 'sort_order' => 9]);
    BusinessSector::factory()->create(['name' => 'Zapatería', 'code' => 'zapateria', 'sort_order' => 1]);

    $rows = Livewire::test('catalog.business-sector')->get('initialRows');

    expect(collect($rows)->pluck('name')->all())->toBe(['Zapatería', 'Alimentos']);
});

test('the sector editor seeds a new record as active and first in line', function (): void {
    $component = Livewire::test('catalog.business-sector');

    expect(railConfig($component->html(), 'blank')['active'])->toBeTrue()
        ->and($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->sort_order)->toBe(0)
        ->and($component->get('form.data')->description)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| storeBusinessSector — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a sector is created with all of its attributes', function (): void {
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'gastronomia')
        ->set('form.data.name', 'Gastronomía')
        ->set('form.data.description', 'Comida y bebida')
        ->set('form.data.sort_order', 3)
        ->call('create')
        ->assertHasNoErrors();

    $sector = BusinessSector::where('code', 'gastronomia')->sole();

    expect($sector->name)->toBe('Gastronomía')
        ->and($sector->description)->toBe('Comida y bebida')
        ->and($sector->sort_order)->toBe(3)
        ->and($sector->is_active)->toBeTrue();
});

test('the code is normalized before the unique rule runs, not after', function (): void {
    // The model lowercases the code on save. If the rule validated the raw value,
    // "SALUD" would pass the unique check and then collide in Postgres as "salud".
    BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);

    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'SALUD')
        ->set('form.data.name', 'Salud Integral')
        ->call('create')
        ->assertHasErrors('code');

    expect(BusinessSector::query()->count())->toBe(1);
});

test('a repeated sector name is rejected as a field error, not as a database crash', function (): void {
    BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);

    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'salud-mental')
        ->set('form.data.name', 'Salud')
        ->call('create')
        ->assertHasErrors('name');

    expect(BusinessSector::query()->count())->toBe(1);
});

test('a sector with no code is rejected', function (): void {
    Livewire::test('catalog.business-sector')
        ->set('form.data.name', 'Gastronomía')
        ->call('create')
        ->assertHasErrors('code');

    expect(BusinessSector::query()->count())->toBe(0);
});

test('a code longer than its column is rejected instead of blowing up on save', function (): void {
    // The column is varchar(30); the max:255 that the helper brings is not enough.
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', str_repeat('a', 31))
        ->set('form.data.name', 'Gastronomía')
        ->call('create')
        ->assertHasErrors('code');
});

test('a name with markup is rejected', function (): void {
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'gastronomia')
        ->set('form.data.name', '<script>alert(1)</script>')
        ->call('create')
        ->assertHasErrors('name');
});

test('an empty description is stored as null, not as an empty string', function (): void {
    // Half a table of "present but empty" values is what whereNull never finds.
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'gastronomia')
        ->set('form.data.name', 'Gastronomía')
        ->set('form.data.description', '')
        ->call('create')
        ->assertHasNoErrors();

    expect(BusinessSector::where('code', 'gastronomia')->value('description'))->toBeNull();
});

test('the order posted by the number input as a string is stored as the right integer', function (): void {
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'gastronomia')
        ->set('form.data.name', 'Gastronomía')
        // A real input posts the number as a string, never as an int.
        ->set('form.data.sort_order', '7')
        ->call('create')
        ->assertHasNoErrors();

    expect(BusinessSector::where('code', 'gastronomia')->value('sort_order'))->toBe(7);
});

test('an order above what the column holds is rejected', function (): void {
    // smallint tops out at 32767: without the explicit bound this reaches Postgres.
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'gastronomia')
        ->set('form.data.name', 'Gastronomía')
        ->set('form.data.sort_order', 40000)
        ->call('create')
        ->assertHasErrors('sort_order');
});

test('every attribute the action persists carries a validation rule', function (): void {
    // Guard: validate() returns only the keys that have rules, so an attribute
    // added to transformServiceData() without a rule would be dropped on save.
    $form = new BusinessSectorForm(
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

test('creating a sector hands the refreshed rows back to Alpine', function (): void {
    BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud', 'sort_order' => 1]);

    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'belleza')
        ->set('form.data.name', 'Belleza')
        ->set('form.data.sort_order', 2)
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('name')
                ->all() === ['Salud', 'Belleza'],
        );
});

test('the create toast is announced in the masculine', function (): void {
    Livewire::test('catalog.business-sector')
        ->set('form.data.code', 'gastronomia')
        ->set('form.data.name', 'Gastronomía')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Rubro creado correctamente');
});
