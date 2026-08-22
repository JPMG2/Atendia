<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateCountry;
use App\Livewire\Forms\Catalog\CountryForm;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

// RefreshDatabase is commented out globally in tests/Pest.php, so these tests would
// otherwise hit countries left over from previous runs (unique code violations).
uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it into a
    // real CountryDto. Without that, a `wire:model="form.data.code"` update throws
    // "Cannot assign array to property ...CountryDto" because Livewire cannot recurse into null.
    Livewire::test('catalog.country')
        ->assertSet('form.data.code', '')
        ->set('form.data.code', 'ARG')
        ->assertSet('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->assertSet('form.data.name', 'Argentina');
});

test('the country table hands its rows to Alpine so the search filters client-side', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    // Rows come from the embedded payload and are rendered by Alpine, so typing in
    // the search box filters without a round-trip to the server.
    $html = Livewire::test('catalog.country')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($country->name);
});

test('every row carries its id, because the code is user-editable and cannot identify a row', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    // Without the id, renaming ARG to ART would leave the update with no stable
    // reference to the row it came from.
    $html = Livewire::test('catalog.country')->html();

    expect($html)->toContain(':key="row.id"');

    // Js::from escapes the payload for a JS string literal, so unwrap it before asserting.
    preg_match("/items: JSON\.parse\('(.*?)'\)/", $html, $matches);
    $payload = json_decode(json_decode('"'.$matches[1].'"'), true);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['id'])->toBe($country->id);
});

test('every row carries the currency code, so the table shows it without a query per row', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $currency->id]);

    $rows = Livewire::test('catalog.country')->get('initialRows');

    expect($rows[0]['currency'])->toBe('ARS');
});

test('the country payload escapes names that would break the Alpine expression', function (): void {
    Country::factory()->create(['code' => 'CIV', 'name' => "Côte d'Ivoire"]);

    // A raw apostrophe used to land inside a JS string literal and break the component.
    expect(Livewire::test('catalog.country')->html())->not->toContain("d'Ivoire");
});

test('the country editor renders its real inputs', function (): void {
    Livewire::test('catalog.country')
        ->assertSee('Código ISO')
        ->assertSee('Nombre')
        ->assertSee('Código telefónico')
        ->assertSee('Moneda')
        ->assertSee('Estado');
});

test('the currency select lists every currency, so a country on a disabled currency keeps it', function (): void {
    // Filtering by is_active here would blank the select when opening such a
    // country, and saving would silently move it to another currency.
    $active = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    $disabled = Currency::factory()->create(['code' => 'VES', 'name' => 'Bolívar', 'is_active' => false]);

    $options = Livewire::test('catalog.country')->instance()->currencyOptions;

    expect(collect($options)->pluck('value')->all())->toEqualCanonicalizing([$active->id, $disabled->id]);
});

test('the country maqueta seeds its editor with sensible defaults', function (): void {
    // Defaults live in the CountryDto / Alpine state: a new row starts active
    // with no currency picked yet.
    $component = Livewire::test('catalog.country');

    // El default del alta viaja en la config `blank` del riel compartido.
    expect(railConfig($component->html(), 'blank')['active'])->toBeTrue();

    expect($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->currency_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| storeCountry — validation of every persisted attribute
|--------------------------------------------------------------------------
| The form saves the VALIDATED payload, so an attribute without a rule would
| be silently dropped, and an attribute with a wrong rule blocks real data.
*/

test('a country is created with its currency, code and phone code', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->set('form.data.phone_code', '54')
        ->call('create')
        ->assertHasNoErrors();

    expect(Country::where('code', 'ARG')->value('phone_code'))->toBe('54');
});

test('a country without a phone code is accepted, because the column is nullable', function (): void {
    $currency = Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'USA')
        ->set('form.data.name', 'Estados Unidos')
        ->set('form.data.phone_code', '')
        ->call('create')
        ->assertHasNoErrors();

    expect(Country::where('code', 'USA')->value('phone_code'))->toBeNull();
});

test('a country with no currency is rejected as a field error, not as a foreign key crash', function (): void {
    // The FK is `constrained()`: without the rule this would blow up inside
    // tryAction and surface as a vague toast instead of an error on the select.
    Livewire::test('catalog.country')
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->call('create')
        ->assertHasErrors('currency_id');

    expect(Country::where('code', 'ARG')->exists())->toBeFalse();
});

test('a currency id that does not exist is rejected', function (): void {
    Livewire::test('catalog.country')
        ->set('form.data.currency_id', 999999)
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->call('create')
        ->assertHasErrors('currency_id');

    expect(Country::query()->count())->toBe(0);
});

test('a code longer than three letters is rejected, since the maxlength attribute is client-side only', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'ABCDEFGHIJ')
        ->set('form.data.name', 'Código larguísimo')
        ->call('create')
        ->assertHasErrors('code');

    expect(Country::where('code', 'ABCDEFGHIJ')->exists())->toBeFalse();
});

test('a phone code longer than the form allows is rejected by validation, not by the database', function (): void {
    // Same lesson as the currency symbol: the input caps at 6, so the rule has to
    // cap at 6 too instead of letting the generic max:255 through.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->set('form.data.phone_code', '123456789')
        ->call('create')
        ->assertHasErrors('phone_code');

    expect(Country::where('code', 'ARG')->exists())->toBeFalse();
});

test('a phone code with letters is rejected', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->set('form.data.phone_code', 'AB12')
        ->call('create')
        ->assertHasErrors('phone_code');
});

test('every attribute the action persists carries a validation rule', function (): void {
    // Guard: validate() returns only the keys that have rules, so an attribute
    // added to transformServiceData() without a rule would be dropped on save.
    $form = new CountryForm(
        // Bare host component: Form::__construct() needs one, nothing more.
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

test('creating a country hands the refreshed rows back to Alpine', function (): void {
    // The table is rendered by Alpine from `items`, seeded once by `x-data`.
    // Livewire's morph preserves Alpine state and never re-evaluates `x-data`,
    // so without this event the new row stays invisible even after "Volver".
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $currency->id]);

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'BOL')
        ->set('form.data.name', 'Bolivia')
        ->set('form.data.phone_code', '591')
        ->set('form.data.is_active', true)
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('code')
                ->all() === ['ARG', 'BOL'],
        );
});

test('the table listens for the refreshed rows event', function (): void {
    expect(Livewire::test('catalog.country')->html())
        ->toContain('x-on:catalog-rows-refreshed="items = $event.detail.rows"');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    // Force the action to blow up so store() returns an error DTO
    // (a validation failure would throw earlier and never reach this branch).
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $this->mock(
        CreateCountry::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.country')
        ->set('form.data.currency_id', $currency->id)
        ->set('form.data.code', 'ARG')
        ->set('form.data.name', 'Argentina')
        ->set('form.data.phone_code', '54')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        // Nothing was saved, so the table must not be reloaded either.
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.data.code', 'ARG')
        ->assertSet('form.data.name', 'Argentina')
        ->assertSet('form.data.phone_code', '54');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    // Every public method of a Livewire component is callable as $wire.method()
    // from the console. Internal helpers must not be part of that surface.
    $component = Livewire::test('catalog.country')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    // A typo in a key makes __() return the key itself, and the user reads
    // "catalog.country.create" on screen. This catches that.
    $html = Livewire::test('catalog.country')->html();

    expect($html)->not->toContain('catalog.country.')
        ->not->toContain('catalog.common.')
        // The copy is really rendered, not just absent.
        ->toContain(__('catalog.country.create'))
        ->toContain(__('catalog.country.empty'))
        ->toContain(__('catalog.common.save'));
});

test('the Alpine ternaries get their copy from lang too, not from hardcoded JS literals', function (): void {
    // Checked on the SOURCE, not on the output: Js::from(__('...')) renders to
    // 'Activa', byte for byte the same as the hardcoded literal, so the HTML cannot
    // tell them apart. x-text expressions are the easiest place to leave copy behind.
    // The chrome copy ("Editando", "Guardar cambios") moved to the shared
    // <x-catalog.form-shell> and is covered by CatalogComponentsTest.
    $source = file_get_contents(resource_path('views/components/catalog/⚡country.blade.php'));

    expect($source)->not->toContain("? 'Activo' : 'Inactivo'")
        ->toContain("Js::from(__('catalog.country.status.active'))");
});
