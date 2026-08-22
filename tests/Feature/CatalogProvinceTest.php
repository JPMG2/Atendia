<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateProvince;
use App\Livewire\Forms\Catalog\ProvinceForm;
use App\Models\Country;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

// RefreshDatabase is commented out globally in tests/Pest.php, so these tests would
// otherwise hit provinces left over from previous runs.
uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it into a
    // real ProvinceDto. Without that, a `wire:model="form.data.name"` update
    // throws "Cannot assign array to property" because Livewire cannot recurse into null.
    Livewire::test('catalog.province')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Buenos Aires')
        ->assertSet('form.data.name', 'Buenos Aires');
});

test('the province table hands its rows to Alpine so the search filters client-side', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    $html = Livewire::test('catalog.province')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($province->name);
});

test('every row carries its id, because the name is user-editable and cannot identify a row', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    $html = Livewire::test('catalog.province')->html();

    expect($html)->toContain(':key="row.id"');

    expect(railConfig($html, 'items'))->toHaveCount(1)
        ->and(railConfig($html, 'items')[0]['id'])->toBe($province->id);
});

test('every row carries the country name, so the table shows it without a query per row', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $country->id]);

    $rows = Livewire::test('catalog.province')->get('initialRows');

    expect($rows[0]['country'])->toBe('Argentina');
});

test('the province editor renders its real inputs', function (): void {
    Livewire::test('catalog.province')
        ->assertSee('Nombre')
        ->assertSee('País')
        ->assertSee('Estado');
});

test('the country combobox lists every country, so a province on a disabled country keeps it', function (): void {
    // Filtering by is_active here would blank the combobox when opening such a
    // province, and saving would silently move it to another country.
    $active = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $disabled = Country::factory()->create(['code' => 'VEN', 'name' => 'Venezuela', 'is_active' => false]);

    $options = Livewire::test('catalog.province')->instance()->countryOptions;

    expect(collect($options)->pluck('value')->all())->toEqualCanonicalizing([$active->id, $disabled->id]);
});

test('the province editor seeds a new record as active', function (): void {
    $component = Livewire::test('catalog.province');

    expect($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->country_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| storeProvince — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a province is created with its country', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.province')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.name', 'Buenos Aires')
        ->call('create')
        ->assertHasNoErrors();

    expect(Province::where('name', 'Buenos Aires')->value('country_id'))->toBe($country->id);
});

test('a province with no country is rejected as a field error, not as a foreign key crash', function (): void {
    // The FK is `constrained()`: without the rule this would blow up inside
    // tryAction and surface as a vague toast instead of an error on the combobox.
    Livewire::test('catalog.province')
        ->set('form.data.name', 'Buenos Aires')
        ->call('create')
        ->assertHasErrors('country_id');

    expect(Province::query()->count())->toBe(0);
});

test('a country id that does not exist is rejected', function (): void {
    Livewire::test('catalog.province')
        ->set('form.data.country_id', 999999)
        ->set('form.data.name', 'Buenos Aires')
        ->call('create')
        ->assertHasErrors('country_id');

    expect(Province::query()->count())->toBe(0);
});

test('the same province name is rejected inside one country', function (): void {
    $venezuela = Country::factory()->create(['code' => 'VEN', 'name' => 'Venezuela']);
    Province::factory()->create(['name' => 'Mérida', 'country_id' => $venezuela->id]);

    Livewire::test('catalog.province')
        ->set('form.data.country_id', $venezuela->id)
        ->set('form.data.name', 'Mérida')
        ->call('create')
        ->assertHasErrors('name');

    expect(Province::query()->count())->toBe(1);
});

test('the same province name IS accepted in every other country', function (): void {
    // This is why the unique rule is scoped by country instead of being global:
    // Mérida is a real province of Venezuela, of Mexico AND of Spain. A global
    // unique would reject the second and the third; no rule at all would let
    // Venezuela hold two Méridas. The uniqueness lives in the validation layer,
    // not in a database constraint — the index on (country_id, name) is plain.
    $countries = collect(['VEN' => 'Venezuela', 'MEX' => 'México', 'ESP' => 'España'])
        ->map(fn (string $name, string $code) => Country::factory()->create(['code' => $code, 'name' => $name]));

    foreach ($countries as $country) {
        Livewire::test('catalog.province')
            ->set('form.data.country_id', $country->id)
            ->set('form.data.name', 'Mérida')
            ->call('create')
            ->assertHasNoErrors();
    }

    expect(Province::where('name', 'Mérida')->count())->toBe(3);
});

test('a name with markup is rejected', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.province')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.name', '<script>alert(1)</script>')
        ->call('create')
        ->assertHasErrors('name');
});

test('every attribute the action persists carries a validation rule', function (): void {
    // Guard: validate() returns only the keys that have rules, so an attribute
    // added to transformServiceData() without a rule would be dropped on save.
    $form = new ProvinceForm(
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

test('creating a province hands the refreshed rows back to Alpine', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $country->id]);

    Livewire::test('catalog.province')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.name', 'Catamarca')
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('name')
                ->all() === ['Buenos Aires', 'Catamarca'],
        );
});

test('the success toast names the entity in the feminine', function (): void {
    // NotificationService maps the TABLE to its Spanish name and gender. Without
    // the row the toast just says "Registro".
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.province')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.name', 'Buenos Aires')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Provincia creada correctamente');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    $this->mock(
        CreateProvince::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.province')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.name', 'Buenos Aires')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.data.name', 'Buenos Aires');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    $component = Livewire::test('catalog.province')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    $html = Livewire::test('catalog.province')->html();

    expect($html)->not->toContain('catalog.province.')
        ->not->toContain('catalog.common.')
        ->toContain(__('catalog.province.create'))
        ->toContain(__('catalog.province.empty'))
        ->toContain(__('catalog.common.save'));
});
