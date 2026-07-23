<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Currency;
use App\Models\TaxCondition;
use Database\Seeders\TaxConditionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Seed just the countries the tax-condition seeder depends on.
 */
function seedCountries(): void
{
    $currencyId = Currency::factory()->create()->id;

    Country::factory()->create(['code' => 'ARG', 'currency_id' => $currencyId]);
    Country::factory()->create(['code' => 'VEN', 'currency_id' => $currencyId]);
}

test('it seeds tax conditions for argentina and venezuela', function () {
    seedCountries();

    $this->seed(TaxConditionSeeder::class);

    expect(TaxCondition::count())->toBe(10);

    $ar = Country::where('code', 'ARG')->value('id');
    $ve = Country::where('code', 'VEN')->value('id');

    expect(TaxCondition::where('country_id', $ar)->count())->toBe(5);
    expect(TaxCondition::where('country_id', $ve)->count())->toBe(5);

    // Venezuela: contribuyentes que discriminan IVA.
    expect(
        TaxCondition::where('country_id', $ve)->where('code', 'CO')->value('discriminate_tax')
    )->toBeTrue();
});

test('the seeder is idempotent', function () {
    seedCountries();

    $this->seed(TaxConditionSeeder::class);
    $this->seed(TaxConditionSeeder::class);

    expect(TaxCondition::count())->toBe(10);
});

test('the same code can exist in different countries', function () {
    $currencyId = Currency::factory()->create()->id;
    $ar = Country::factory()->create(['code' => 'ARG', 'currency_id' => $currencyId]);
    $ve = Country::factory()->create(['code' => 'VEN', 'currency_id' => $currencyId]);

    TaxCondition::factory()->create(['country_id' => $ar->id, 'code' => 'XX', 'name' => 'A']);

    // Reusing the same code under a different country must be allowed.
    TaxCondition::factory()->create(['country_id' => $ve->id, 'code' => 'XX', 'name' => 'B']);

    expect(TaxCondition::where('code', 'XX')->count())->toBe(2);
});

test('a duplicated code within the same country is rejected', function () {
    $currencyId = Currency::factory()->create()->id;
    $ar = Country::factory()->create(['code' => 'ARG', 'currency_id' => $currencyId]);

    TaxCondition::factory()->create(['country_id' => $ar->id, 'code' => 'XX', 'name' => 'A']);

    TaxCondition::factory()->create(['country_id' => $ar->id, 'code' => 'XX', 'name' => 'B']);
})->throws(QueryException::class);

test('a tax condition belongs to a country', function () {
    $condition = TaxCondition::factory()->create();

    expect($condition->country)->toBeInstanceOf(Country::class);
});
