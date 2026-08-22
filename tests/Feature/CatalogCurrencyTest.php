<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateCurrency;
use App\Livewire\Forms\Catalog\CurrencyForm;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

// RefreshDatabase is commented out globally in tests/Pest.php, so these tests would
// otherwise hit currencies left over from previous runs (unique code violations).
uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it into a
    // real CurrencyDto. Without that, a `wire:model="form.data.code"` update throws
    // "Cannot assign array to property ...CurrencyDto" because Livewire cannot recurse into null.
    Livewire::test('catalog.currency')
        ->assertSet('form.data.code', '')
        ->set('form.data.code', 'USD')
        ->assertSet('form.data.code', 'USD')
        ->set('form.data.name', 'Dólar Estadounidense')
        ->assertSet('form.data.name', 'Dólar Estadounidense');
});

test('the currency table hands its rows to Alpine so the search filters client-side', function (): void {
    // Assert against what was actually persisted rather than the literal handed to
    // the factory: the model still trims and collapses whitespace on set.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    // Rows come from the embedded payload and are rendered by Alpine, so typing in
    // the search box filters without a round-trip to the server.
    $html = Livewire::test('catalog.currency')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($currency->name);
});

test('every row carries its id, because the code is user-editable and cannot identify a row', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    // Without the id, renaming ARS to ARP would leave the update with no stable
    // reference to the row it came from.
    $html = Livewire::test('catalog.currency')->html();

    expect($html)->toContain(':key="row.id"');

    // @js escapes the payload for a JS string literal, so unwrap it before asserting.
    preg_match("/items: JSON\.parse\('(.*?)'\)/", $html, $matches);
    $payload = json_decode(json_decode('"'.$matches[1].'"'), true);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['id'])->toBe($currency->id);
});

test('the currency payload escapes names that would break the Alpine expression', function (): void {
    Currency::factory()->create(['code' => 'XOF', 'name' => "Franco CFA d'Afrique"]);

    // A raw apostrophe used to land inside a JS string literal and break the component.
    expect(Livewire::test('catalog.currency')->html())->not->toContain("d'Afrique");
});

test('the currency editor renders its real inputs', function (): void {
    Livewire::test('catalog.currency')
        ->assertSee('Código ISO')
        ->assertSee('Nombre')
        ->assertSee('Símbolo')
        ->assertSee('Decimales')
        ->assertSee('Estado');
});

test('the currency maqueta seeds its editor with sensible defaults', function (): void {
    // Los defaults del alta viven en el DTO, que es el único estado del
    // formulario: los campos van por wire:model contra él, no contra Alpine.
    $data = Livewire::test('catalog.currency')->get('form.data');

    expect($data->decimal_places)->toBe(2)
        ->and($data->is_active)->toBeTrue();
});

test('the currency form wires the front-end validation (Alpine component + per-input error)', function (): void {
    Livewire::test('catalog.currency')
        ->assertSeeHtml('x-data="currencyForm"') // per-component Alpine (defined in @script)
        ->assertSeeHtml('errors.code')           // per-input Alpine error binding
        ->assertSeeHtml('x-bind:aria-invalid');  // red-border hook driven by Alpine
})->skip('Front-end validation wiring is pending — the currency editor is still a maqueta (no form connected to the inputs yet).');

/*
|--------------------------------------------------------------------------
| storeCurrency — validation of every persisted attribute
|--------------------------------------------------------------------------
| The form saves the VALIDATED payload, so an attribute without a rule would
| be silently dropped, and an attribute with a wrong rule blocks real data.
*/

test('a one-character symbol is accepted, because $ and € are single glyphs', function (): void {
    Livewire::test('catalog.currency')
        ->set('form.data.code', 'USD')
        ->set('form.data.name', 'Dólar estadounidense')
        ->set('form.data.symbol', '$')
        ->set('form.data.decimal_places', 2)
        ->call('create')
        ->assertHasNoErrors();

    expect(Currency::where('code', 'USD')->value('symbol'))->toBe('$');
});

test('a three-letter name is accepted, because "Yen" is a real currency name', function (): void {
    Livewire::test('catalog.currency')
        ->set('form.data.code', 'JPY')
        ->set('form.data.name', 'Yen')
        ->set('form.data.symbol', '¥')
        ->set('form.data.decimal_places', 0)
        ->call('create')
        ->assertHasNoErrors();

    expect(Currency::where('code', 'JPY')->exists())->toBeTrue();
});

test('a code longer than three letters is rejected, since the maxlength attribute is client-side only', function (): void {
    Livewire::test('catalog.currency')
        ->set('form.data.code', 'ABCDEFGHIJ')
        ->set('form.data.name', 'Código larguísimo')
        ->set('form.data.symbol', 'X')
        ->call('create')
        ->assertHasErrors('code');

    expect(Currency::where('code', 'ABCDEFGHIJ')->exists())->toBeFalse();
});

test('decimal_places is validated, so a crafted request cannot store an absurd precision', function (): void {
    // This used to persist 9999: the field had no rule and the payload was
    // re-transformed after validation instead of being taken from it.
    Livewire::test('catalog.currency')
        ->set('form.data.code', 'ZZZ')
        ->set('form.data.name', 'Moneda de prueba')
        ->set('form.data.symbol', 'Z')
        ->set('form.data.decimal_places', 9999)
        ->call('create')
        ->assertHasErrors('decimal_places');

    expect(Currency::where('code', 'ZZZ')->exists())->toBeFalse();
});

test('every attribute the action persists carries a validation rule', function (): void {
    // Guard: validate() returns only the keys that have rules, so an attribute
    // added to transformServiceData() without a rule would be dropped on save.
    $form = new CurrencyForm(
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

test('creating a currency hands the refreshed rows back to Alpine', function (): void {
    // The table is rendered by Alpine from `items`, seeded once by `x-data`.
    // Livewire's morph preserves Alpine state and never re-evaluates `x-data`,
    // so without this event the new row stays invisible even after "Volver".
    Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    Livewire::test('catalog.currency')
        ->set('form.data.code', 'USD')
        ->set('form.data.name', 'Dólar Estadounidense')
        ->set('form.data.symbol', 'US$')
        ->set('form.data.decimal_places', 2)
        ->set('form.data.is_active', true)
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('code')
                ->all() === ['ARS', 'USD'],
        );
});

test('the table listens for the refreshed rows event', function (): void {
    expect(Livewire::test('catalog.currency')->html())
        ->toContain('x-on:catalog-rows-refreshed="items = $event.detail.rows"');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    // Force the action to blow up so store() returns an error DTO
    // (a validation failure would throw earlier and never reach this branch).
    $this->mock(
        CreateCurrency::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.currency')
        ->set('form.data.code', 'USD')
        ->set('form.data.name', 'Dólar Estadounidense')
        ->set('form.data.symbol', 'US$')
        ->set('form.data.decimal_places', 2)
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        // Nothing was saved, so the table must not be reloaded either.
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.data.code', 'USD')
        ->assertSet('form.data.name', 'Dólar Estadounidense')
        ->assertSet('form.data.symbol', 'US$');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    // Every public method of a Livewire component is callable as $wire.method()
    // from the console. Internal helpers must not be part of that surface.
    $component = Livewire::test('catalog.currency')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    // A typo in a key makes __() return the key itself, and the user reads
    // "catalog.currency.create" on screen. This catches that.
    $html = Livewire::test('catalog.currency')->html();

    expect($html)->not->toContain('catalog.currency.')
        ->not->toContain('catalog.common.')
        // The copy is really rendered, not just absent.
        ->toContain(__('catalog.currency.create'))
        ->toContain(__('catalog.currency.empty'))
        ->toContain(__('catalog.common.save'));
});

test('the Alpine ternaries get their copy from lang too, not from hardcoded JS literals', function (): void {
    // Checked on the SOURCE, not on the output: Js::from(__('...')) renders to
    // 'Activa', byte for byte the same as the hardcoded literal, so the HTML cannot
    // tell them apart. x-text expressions are the easiest place to leave copy behind.
    // The chrome copy ("Editando", "Guardar cambios") moved to the shared
    // <x-catalog.form-shell> and is covered by CatalogComponentsTest.
    $source = file_get_contents(resource_path('views/components/catalog/⚡currency.blade.php'));

    expect($source)->not->toContain("? 'Activa' : 'Inactiva'")
        ->toContain("Js::from(__('catalog.currency.status.active'))");
});
