<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateRegion;
use App\Livewire\Forms\Catalog\RegionForm;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.region')
        ->assertSet('form.regionData.name', '')
        ->set('form.regionData.name', 'Zona Norte')
        ->assertSet('form.regionData.name', 'Zona Norte');
});

test('the region table hands its rows to Alpine so the search filters client-side', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);

    $html = Livewire::test('catalog.region')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($region->name);
});

test('every row carries its id and its province name', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    $region = Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $province->id]);

    $rows = Livewire::test('catalog.region')->get('initialRows');

    expect($rows[0]['id'])->toBe($region->id)
        ->and($rows[0]['province'])->toBe('Buenos Aires');
});

test('the region editor renders its real inputs', function (): void {
    Livewire::test('catalog.region')
        ->assertSee('Nombre')
        ->assertSee('Provincia')
        ->assertSee('Estado');
});

test('the province combobox labels each option with its country', function (): void {
    // Province names repeat across countries ("Córdoba" is in Argentina and in
    // Spain): without the country the combobox shows two identical options and
    // there is no way to tell which is which.
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    Province::factory()->create(['name' => 'Córdoba', 'country_id' => $argentina->id]);

    $options = Livewire::test('catalog.region')->instance()->provinceOptions;

    expect($options[0]['label'])->toBe('Córdoba — ARG');
});

test('the province combobox lists every province, so a region on a disabled one keeps it', function (): void {
    $active = Province::factory()->create(['name' => 'Buenos Aires']);
    $disabled = Province::factory()->create(['name' => 'Santa Fe', 'is_active' => false]);

    $options = Livewire::test('catalog.region')->instance()->provinceOptions;

    expect(collect($options)->pluck('value')->all())->toEqualCanonicalizing([$active->id, $disabled->id]);
});

/*
|--------------------------------------------------------------------------
| storeRegion — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a region is created with its province', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', $province->id)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertHasNoErrors();

    expect(Region::where('name', 'Zona Norte')->value('province_id'))->toBe($province->id);
});

test('a region with no province is rejected as a field error, not as a foreign key crash', function (): void {
    Livewire::test('catalog.region')
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertHasErrors('province_id');

    expect(Region::query()->count())->toBe(0);
});

test('a province id that does not exist is rejected', function (): void {
    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', 999999)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertHasErrors('province_id');

    expect(Region::query()->count())->toBe(0);
});

test('the same region name is rejected inside one province', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $province->id]);

    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', $province->id)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertHasErrors('name');

    expect(Region::query()->count())->toBe(1);
});

test('the same region name IS accepted in another province', function (): void {
    // "Zona Norte" exists in every province; a global unique would only let the
    // first one through.
    $buenosAires = Province::factory()->create(['name' => 'Buenos Aires']);
    $santaFe = Province::factory()->create(['name' => 'Santa Fe']);
    Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $buenosAires->id]);

    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', $santaFe->id)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertHasNoErrors();

    expect(Region::where('name', 'Zona Norte')->count())->toBe(2);
});

test('every attribute the action persists carries a validation rule', function (): void {
    $form = new RegionForm(
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

test('creating a region hands the refreshed rows back to Alpine', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);
    Region::factory()->create(['name' => 'Zona Este', 'province_id' => $province->id]);

    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', $province->id)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('name')
                ->all() === ['Zona Este', 'Zona Norte'],
        );
});

test('the success toast names the entity in the feminine', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', $province->id)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Región creada correctamente');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    $province = Province::factory()->create(['name' => 'Buenos Aires']);

    $this->mock(
        CreateRegion::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.region')
        ->set('form.regionData.province_id', $province->id)
        ->set('form.regionData.name', 'Zona Norte')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.regionData.name', 'Zona Norte');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    $component = Livewire::test('catalog.region')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    $html = Livewire::test('catalog.region')->html();

    expect($html)->not->toContain('catalog.region.')
        ->not->toContain('catalog.common.')
        ->toContain(__('catalog.region.create'))
        ->toContain(__('catalog.region.empty'));
});
