<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Province;
use App\Models\Region;
use App\Models\ServiceModality;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;

// RefreshDatabase is commented out globally in tests/Pest.php, so these tests would
// otherwise see catalog rows left over from previous runs and break the ordering assertions.
uses(RefreshDatabase::class);

test('currency options are shaped for the combobox and sorted by code', function (): void {
    Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);
    Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    expect(Currency::options())->toBe([
        ['value' => Currency::where('code', 'ARS')->value('id'), 'label' => 'ARS — Peso Argentino'],
        ['value' => Currency::where('code', 'USD')->value('id'), 'label' => 'USD — Dólar Estadounidense'],
    ]);
});

test('country options are shaped for the combobox and sorted by name', function (): void {
    Country::factory()->create(['code' => 'VEN', 'name' => 'Venezuela']);
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    expect(Country::options())->toBe([
        ['value' => Country::where('code', 'ARG')->value('id'), 'label' => 'ARG — Argentina'],
        ['value' => Country::where('code', 'VEN')->value('id'), 'label' => 'VEN — Venezuela'],
    ]);
});

// The combobox resolves the selected option by looking the id up inside `options`
// (resources/js/combobox.js). If a deactivated record were dropped from the list,
// editing a row that still points at it would render an empty field holding a value.
test('the default takes no filter at all, so deactivated records stay in the list', function (): void {
    $retired = Currency::factory()->create(['code' => 'VEF', 'name' => 'Bolívar Fuerte', 'is_active' => false]);
    Currency::factory()->create(['code' => 'VES', 'name' => 'Bolívar Soberano']);

    expect(Currency::options())->toHaveCount(2)
        ->and(array_column(Currency::options(), 'value'))->toContain($retired->id);

    $inactiveCountry = Country::factory()->create(['code' => 'XXA', 'name' => 'País Dado de Baja', 'is_active' => false]);

    expect(array_column(Country::options(), 'value'))->toContain($inactiveCountry->id);
});

test('passing states filters the list, and each state can be asked for on its own', function (): void {
    $active = Currency::factory()->create(['code' => 'VES', 'name' => 'Bolívar Soberano']);
    $retired = Currency::factory()->create(['code' => 'VEF', 'name' => 'Bolívar Fuerte', 'is_active' => false]);

    expect(array_column(Currency::options([false]), 'value'))->toBe([$retired->id])
        ->and(array_column(Currency::options([true]), 'value'))->toBe([$active->id])
        ->and(array_column(Currency::options([true, false]), 'value'))->toBe([$retired->id, $active->id]);
});

test('options come back empty when the catalog has no rows', function (): void {
    expect(Currency::options())->toBe([])
        ->and(Country::options())->toBe([]);
});

// The province label carries the country code because province names repeat across
// countries ("Córdoba" exists in Argentina and in Spain): without it the combobox
// shows two identical rows.
test('province options carry the country code and are sorted by name', function (): void {
    $spain = Country::factory()->create(['code' => 'ESP', 'name' => 'España']);
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    Province::factory()->create(['name' => 'Córdoba', 'country_id' => $spain->id]);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $argentina->id]);

    expect(array_column(Province::options(), 'label'))
        ->toBe(['Buenos Aires — ARG', 'Córdoba — ESP']);
});

// Both masters order by `sort_order` first: the catalog decides what shows up on top,
// not the alphabet.
test('sector and modality options are sorted by sort_order before name', function (): void {
    BusinessSector::factory()->create(['name' => 'Abogacía', 'sort_order' => 20]);
    BusinessSector::factory()->create(['name' => 'Zapatería', 'sort_order' => 10]);

    ServiceModality::factory()->create(['name' => 'Abono', 'sort_order' => 20]);
    ServiceModality::factory()->create(['name' => 'Turno', 'sort_order' => 10]);

    expect(array_column(BusinessSector::options(), 'label'))->toBe(['Zapatería', 'Abogacía'])
        ->and(array_column(ServiceModality::options(), 'label'))->toBe(['Turno', 'Abono']);
});

// The wizard chips speak in codes — the value closure is opt-in, so the
// combobox callers keep receiving ids untouched.
test('sector options can carry the code as value while the default stays the id', function (): void {
    $sector = BusinessSector::factory()->create(['code' => 'salud']);

    expect(array_column(BusinessSector::options(value: fn (BusinessSector $s): string => $s->code), 'value'))->toBe(['salud'])
        ->and(array_column(BusinessSector::options(), 'value'))->toBe([$sector->id]);
});

// The three masters the company screen picks from are chosen by name, so their
// default label is the bare name — no code, no parent, nothing to disambiguate.
test('region, tax condition and social network options carry the bare name', function (): void {
    Region::factory()->create(['name' => 'Zona Sur']);
    Region::factory()->create(['name' => 'Centro']);

    TaxCondition::factory()->create(['code' => 'RI', 'name' => 'Responsable Inscripto']);
    TaxCondition::factory()->create(['code' => 'MT', 'name' => 'Monotributista']);

    SocialNetwork::factory()->create(['name' => 'Instagram', 'abbreviation' => 'IG']);
    SocialNetwork::factory()->create(['name' => 'Facebook', 'abbreviation' => 'FB']);

    expect(array_column(Region::options(), 'label'))->toBe(['Centro', 'Zona Sur'])
        ->and(array_column(TaxCondition::options(), 'label'))->toBe(['Monotributista', 'Responsable Inscripto'])
        ->and(array_column(SocialNetwork::options(), 'label'))->toBe(['Facebook', 'Instagram']);
});

// The whole point of the label being a parameter: a screen that wants a different
// text says so at the call site, and nobody else has to be visited.
test('a caller can pass its own label without disturbing the default', function (): void {
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $argentina->id]);

    $plainName = fn (Country $country): string => $country->name;

    expect(array_column(Country::options(label: $plainName), 'label'))->toBe(['Argentina'])
        ->and(array_column(Province::options(label: fn (Province $p): string => $p->name), 'label'))
        ->toBe(['Buenos Aires'])
        // The default is untouched by the call above: the catalog keeps its code.
        ->and(array_column(Country::options(), 'label'))->toBe(['ARG — Argentina'])
        ->and(array_column(Province::options(), 'label'))->toBe(['Buenos Aires — ARG']);
});

// The company screen asks for the active rows only, and it does so through the
// same states filter every other caller uses.
test('a custom label still honours the states filter', function (): void {
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    Country::factory()->create(['code' => 'XXA', 'name' => 'País Dado de Baja', 'is_active' => false]);

    expect(array_column(Country::options(states: [true], label: fn (Country $c): string => $c->name), 'label'))
        ->toBe(['Argentina']);
});

test('every catalog options() takes the same states filter', function (): void {
    $models = [
        Currency::class,
        Country::class,
        Province::class,
        Region::class,
        TaxCondition::class,
        SocialNetwork::class,
        BusinessSector::class,
        ServiceModality::class,
    ];

    foreach ($models as $model) {
        $model::factory()->create(['is_active' => false]);
        $model::factory()->create(['is_active' => true]);

        expect($model::options())->toHaveCount(2, $model)
            ->and($model::options([false]))->toHaveCount(1, $model)
            ->and($model::options([true]))->toHaveCount(1, $model);
    }
});

// The masters whose label already varies between screens take it the same way,
// so a caller never has to check which one it is talking to. Currency, sector and
// modality are left out on purpose: no screen asks them for a second text yet.
test('the masters that accept a label all take it the same way', function (): void {
    $models = [
        Country::class,
        Province::class,
        Region::class,
        TaxCondition::class,
        SocialNetwork::class,
    ];

    foreach ($models as $model) {
        $model::factory()->create(['name' => 'Fijo']);

        expect(array_column($model::options(label: fn ($record): string => 'X'.$record->id), 'label'))
            ->toBe(['X'.$model::query()->value('id')], $model);
    }
});
