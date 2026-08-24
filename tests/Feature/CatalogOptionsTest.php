<?php

declare(strict_types=1);

use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Province;
use App\Models\ServiceModality;
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

test('every catalog options() takes the same states filter', function (): void {
    $models = [Currency::class, Country::class, Province::class, BusinessSector::class, ServiceModality::class];

    foreach ($models as $model) {
        $model::factory()->create(['is_active' => false]);
        $model::factory()->create(['is_active' => true]);

        expect($model::options())->toHaveCount(2, $model)
            ->and($model::options([false]))->toHaveCount(1, $model)
            ->and($model::options([true]))->toHaveCount(1, $model);
    }
});
