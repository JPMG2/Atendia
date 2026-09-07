<?php

declare(strict_types=1);

use App\Dto\BusinessDto;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Currency;
use App\Models\TaxCondition;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('the dto mirrors every column of the businesses table, so a profile slice can hydrate from it', function (): void {
    // The DTO's docblock promises "full row": this pins it, so a new column
    // cannot reach the table without reaching the DTO too.
    $columns = collect(Schema::getColumnListing('businesses'))
        ->diff(['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by']);

    expect((new BusinessDto)->toPayload())->toHaveKeys($columns->all());
});

/*
|--------------------------------------------------------------------------
| The business's full profile — offered, never required
|--------------------------------------------------------------------------
| Every attribute here is nullable on purpose: a seamstress with no tax id
| is invoiced as a natural person, and a business may never pick currencies.
| The reference currency mirrors the Venezuelan "Ref" habit: prices by law
| in the local currency, the real price next to it in dollars.
*/

test('the profile attributes persist and stay optional', function (): void {
    $currency = Currency::factory()->create();
    $reference = Currency::factory()->create();
    $taxCondition = TaxCondition::factory()->create();

    $business = Business::factory()->create([
        'logo_path' => 'logos/negocio.png',
        'address' => 'Av. Bolívar 123',
        'city' => 'Valencia',
        'has_premises' => true,
        'description' => 'Taller de costura con retiro en el local.',
        'currency_id' => $currency->id,
        'reference_currency_id' => $reference->id,
        'tax_condition_id' => $taxCondition->id,
        'tax_id' => 'J-12345678-9',
    ]);

    $business->refresh();

    expect($business->has_premises)->toBeTrue()
        ->and($business->currency->is($currency))->toBeTrue()
        ->and($business->referenceCurrency->is($reference))->toBeTrue()
        ->and($business->taxCondition->is($taxCondition))->toBeTrue();

    $bare = Business::factory()->create();

    expect($bare->logo_path)->toBeNull()
        ->and($bare->has_premises)->toBeNull()
        ->and($bare->currency_id)->toBeNull()
        ->and($bare->tax_condition_id)->toBeNull()
        ->and($bare->tax_id)->toBeNull();
});

test('opening hours hold several shifts per day and come back ordered', function (): void {
    $business = Business::factory()->create();

    // Inserted out of order on purpose: the relation must sort, not the caller.
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '16:00', 'closes_at' => '20:00']);
    $business->hours()->create(['day_of_week' => 0, 'opens_at' => '09:00', 'closes_at' => '13:00']);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);

    $hours = $business->hours()->get();

    expect($hours)->toHaveCount(3)
        ->and($hours->first()->day_of_week)->toBe(0)
        ->and($hours->last()->opens_at)->toBe('16:00:00');
});

test('a business hour never accepts a foreign business id through mass assignment', function (): void {
    $mine = Business::factory()->create();
    $other = Business::factory()->create();

    expect(fn () => $mine->hours()->create([
        'business_id' => $other->id,
        'day_of_week' => 2,
        'opens_at' => '09:00',
        'closes_at' => '18:00',
    ]))->toThrow(MassAssignmentException::class);
});

test('day names come localized from the active locale, never hand-written', function (): void {
    app()->setLocale('es');
    $days = BusinessHour::dayNames();

    expect($days[0])->toBe('Domingo')
        ->and($days[3])->toBe('Miércoles')
        ->and($days)->toHaveCount(7);

    app()->setLocale('en');

    expect(BusinessHour::dayNames()[0])->toBe('Sunday');
});

test('deleting a business takes its hours with it', function (): void {
    $business = Business::factory()->create();
    $business->hours()->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);

    $business->forceDelete();

    expect(BusinessHour::query()->count())->toBe(0);
});
