<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateTaxCondition;
use App\Livewire\Forms\Catalog\TaxConditionForm;
use App\Models\Country;
use App\Models\TaxCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.tax-condition')
        ->assertSet('form.data.code', '')
        ->set('form.data.code', 'RI')
        ->assertSet('form.data.code', 'RI');
});

test('the tax condition table hands its rows to Alpine so the search filters client-side', function (): void {
    $condition = TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto']);

    $html = Livewire::test('catalog.tax-condition')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($condition->name);
});

test('every row carries its id, its country code and whether it discriminates', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $condition = TaxCondition::factory()->create([
        'code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $country->id, 'discriminate_tax' => true,
    ]);

    $rows = Livewire::test('catalog.tax-condition')->get('initialRows');

    expect($rows[0]['id'])->toBe($condition->id)
        ->and($rows[0]['country'])->toBe('ARG')
        ->and($rows[0]['discriminates'])->toBeTrue();
});

test('the tax condition editor renders its real inputs', function (): void {
    Livewire::test('catalog.tax-condition')
        ->assertSee('Código')
        ->assertSee('Nombre')
        ->assertSee('País')
        ->assertSee('Discrimina impuesto')
        ->assertSee('Estado');
});

test('the editor seeds a new record as active and not discriminating', function (): void {
    // `discriminate_tax` defaults to false in the table; the DTO has to agree or
    // a brand new condition would arrive at the form already ticked.
    $component = Livewire::test('catalog.tax-condition');

    expect($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->discriminate_tax)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| storeTaxCondition — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a tax condition is created with its country, code and discrimination flag', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Responsable Inscripto')
        ->set('form.data.discriminate_tax', true)
        ->call('create')
        ->assertHasNoErrors();

    $condition = TaxCondition::where('code', 'RI')->first();

    expect($condition->country_id)->toBe($country->id)
        ->and($condition->discriminate_tax)->toBeTrue();
});

test('a tax condition with no country is rejected as a field error, not as a foreign key crash', function (): void {
    Livewire::test('catalog.tax-condition')
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Responsable Inscripto')
        ->call('create')
        ->assertHasErrors('country_id');

    expect(TaxCondition::query()->count())->toBe(0);
});

test('a duplicate code inside one country is caught as a field error, not as a database crash', function (): void {
    // The table has UNIQUE (country_id, code): without the rule the clash would
    // surface as a Postgres error swallowed by tryAction.
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $country->id]);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Otro nombre')
        ->call('create')
        ->assertHasErrors('code');

    expect(TaxCondition::query()->count())->toBe(1);
});

test('a duplicate name inside one country is caught as a field error too', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $country->id]);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'XX')
        ->set('form.data.name', 'Responsable Inscripto')
        ->call('create')
        ->assertHasErrors('name');

    expect(TaxCondition::query()->count())->toBe(1);
});

test('the same code IS accepted in another country', function (): void {
    // Tax conditions belong to a country: "RI" can exist in Argentina and in
    // another country at the same time. A global unique would reject the second.
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $uruguay = Country::factory()->create(['code' => 'URY', 'name' => 'Uruguay']);
    TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $argentina->id]);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $uruguay->id)
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Responsable Inscripto')
        ->call('create')
        ->assertHasNoErrors();

    expect(TaxCondition::where('code', 'RI')->count())->toBe(2);
});

test('a duplicate code typed in lowercase is caught as a field error, not as a database crash', function (): void {
    // This is why toPayload() normalizes BEFORE validation: `unique` would compare
    // "ri" against the stored "RI", find nothing, and the clash would surface as a
    // Postgres error inside tryAction.
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto', 'country_id' => $country->id]);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'ri')
        ->set('form.data.name', 'Otro nombre')
        ->call('create')
        ->assertHasErrors('code');

    expect(TaxCondition::query()->count())->toBe(1);
});

test('every attribute the action persists carries a validation rule', function (): void {
    // Without a rule, `discriminate_tax` would be dropped from the validated
    // payload and every condition would save as "does not discriminate".
    $form = new TaxConditionForm(
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

test('creating a tax condition hands the refreshed rows back to Alpine', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    TaxCondition::factory()->create(['code' => 'EX', 'name' => 'IVA Exento', 'country_id' => $country->id]);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Responsable Inscripto')
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('code')
                ->all() === ['EX', 'RI'],
        );
});

test('the success toast names the entity in the feminine', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Responsable Inscripto')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Condición fiscal creada correctamente');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    $this->mock(
        CreateTaxCondition::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.tax-condition')
        ->set('form.data.country_id', $country->id)
        ->set('form.data.code', 'RI')
        ->set('form.data.name', 'Responsable Inscripto')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.data.code', 'RI');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    $component = Livewire::test('catalog.tax-condition')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    $html = Livewire::test('catalog.tax-condition')->html();

    expect($html)->not->toContain('catalog.tax_condition.')
        ->not->toContain('catalog.common.')
        ->toContain(__('catalog.tax_condition.create'))
        ->toContain(__('catalog.tax_condition.empty'));
});
