<?php

declare(strict_types=1);

use App\Models\CatalogForm;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\CountryHolidaySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Holidays master
|--------------------------------------------------------------------------
| A holiday could only be a fixed date or a day from Easter: the one a decree
| moves, or a bridge announced in October, had nowhere to go. Now the owner
| types it in from the hub, for ONE year.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
});

function holidayCountry(string $code = 'ARG'): Country
{
    return Country::query()->where('code', $code)->firstOrFail();
}

/** Fills the form the way the browser would and saves it as a new holiday. */
function createHoliday(array $fields)
{
    $component = Livewire::test('catalog.country-holiday')->call('openCreate');

    foreach ($fields as $field => $value) {
        $component->set('form.data.'.$field, $value);
    }

    return $component->call('create');
}

test('the master is a row of the hub, behind its own permission', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $support = User::factory()->create(['email_verified_at' => now()]);
    $support->assignRole('support');

    expect(CatalogForm::visibleTo($admin)->pluck('component')->all())->toContain('catalog.country-holiday')
        ->and(CatalogForm::visibleTo($support)->pluck('component')->all())->not->toContain('catalog.country-holiday');
});

test('a holiday of every year is added and lands on each year', function (): void {
    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Día del Trabajador', 'kind' => 'fixed', 'month' => 5, 'day' => 1])
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(CountryHoliday::forYear(holidayCountry()->id, 2026)->pluck('date')->map->format('Y-m-d')->all())->toBe(['2026-05-01'])
        ->and(CountryHoliday::forYear(holidayCountry()->id, 2027)->pluck('date')->map->format('Y-m-d')->all())->toBe(['2027-05-01']);
});

test('the one a decree moves is typed in for ONE year and is gone the next', function (): void {
    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Feriado puente', 'kind' => 'once', 'on_date' => '2026-12-07'])
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(CountryHoliday::forYear(holidayCountry()->id, 2026)->pluck('name')->all())->toBe(['Feriado puente'])
        ->and(CountryHoliday::forYear(holidayCountry()->id, 2027))->toBeEmpty();

    $row = CountryHoliday::query()->sole();

    // A one-year day carries NO month or day: two dates that could disagree never share a row.
    expect($row->month)->toBeNull()->and($row->day)->toBeNull()->and($row->easter_offset)->toBeNull()
        ->and($row->kind())->toBe('once');
});

test('a day counted from Easter is added from the number of days', function (): void {
    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Viernes Santo', 'kind' => 'easter', 'easter_offset' => -2])
        ->assertHasNoErrors();

    // Easter 2026 is April 5th.
    expect(CountryHoliday::forYear(holidayCountry()->id, 2026)->first()['date']->format('Y-m-d'))->toBe('2026-04-03')
        ->and(CountryHoliday::query()->sole()->describeWhen())->toBe('2 días antes de Pascua');
});

test('each shape asks only for its own date', function (): void {
    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Sin fecha', 'kind' => 'fixed'])
        ->assertHasErrors(['month', 'day']);

    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Sin fecha', 'kind' => 'easter'])
        ->assertHasErrors(['easter_offset']);

    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Sin fecha', 'kind' => 'once'])
        ->assertHasErrors(['on_date']);

    expect(CountryHoliday::query()->count())->toBe(0);
});

test('a day that does not exist in its month is refused, a leap day is not', function (): void {
    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Treinta y uno de abril', 'kind' => 'fixed', 'month' => 4, 'day' => 31])
        ->assertHasErrors(['day']);

    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Día bisiesto', 'kind' => 'fixed', 'month' => 2, 'day' => 29])
        ->assertHasNoErrors();
});

test('a holiday that is switched off is ignored but kept', function (): void {
    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Día del Trabajador', 'kind' => 'fixed', 'month' => 5, 'day' => 1, 'is_active' => false])
        ->assertHasNoErrors();

    expect(CountryHoliday::query()->count())->toBe(1)
        ->and(CountryHoliday::forYear(holidayCountry()->id, 2026))->toBeEmpty();
});

test('editing a holiday changes its shape and clears the date it no longer uses', function (): void {
    $holiday = CountryHoliday::query()->create(['country_id' => holidayCountry()->id, 'name' => 'Día movible', 'month' => 6, 'day' => 17]);

    Livewire::test('catalog.country-holiday')
        ->call('openEdit', $holiday->id)
        ->assertSet('form.data.kind', 'fixed')
        ->set('form.data.kind', 'once')
        ->set('form.data.on_date', '2026-06-15')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    $holiday->refresh();

    expect($holiday->kind())->toBe('once')
        ->and($holiday->month)->toBeNull()
        ->and($holiday->on_date->format('Y-m-d'))->toBe('2026-06-15');
});

test('the list says each date in words', function (): void {
    CountryHoliday::query()->create(['country_id' => holidayCountry()->id, 'name' => 'Día de la Independencia', 'month' => 7, 'day' => 9]);
    CountryHoliday::query()->create(['country_id' => holidayCountry()->id, 'name' => 'Pascua', 'easter_offset' => 0]);
    CountryHoliday::query()->create(['country_id' => holidayCountry()->id, 'name' => 'Puente', 'on_date' => '2026-12-07']);

    $when = (new CountryHoliday)->catalogRows()->pluck('when', 'name');

    expect($when['Día de la Independencia'])->toBe('9 de julio')
        ->and($when['Pascua'])->toBe('Domingo de Pascua')
        ->and($when['Puente'])->toBe('07/12/2026');
});

test('a re-seed never puts back what was renamed or switched off', function (): void {
    $this->seed(CountryHolidaySeeder::class);

    $argentina = holidayCountry();
    $newYear = CountryHoliday::query()->where('country_id', $argentina->id)->where('name', 'Año Nuevo')->firstOrFail();
    $newYear->update(['name' => 'Año Nuevo (renombrado)', 'is_active' => false]);
    $count = CountryHoliday::query()->where('country_id', $argentina->id)->count();

    $this->seed(CountryHolidaySeeder::class);

    expect(CountryHoliday::query()->where('country_id', $argentina->id)->count())->toBe($count)
        ->and(CountryHoliday::query()->where('name', 'Año Nuevo')->where('country_id', $argentina->id)->exists())->toBeFalse();
});

test('who added or moved a day stays in the audit trail', function (): void {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($admin);

    createHoliday(['country_id' => holidayCountry()->id, 'name' => 'Feriado puente', 'kind' => 'once', 'on_date' => '2026-12-07'])->assertHasNoErrors();

    $entry = Activity::query()->where('subject_type', CountryHoliday::class)->latest('id')->firstOrFail();

    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->description)->toBe('created');
});
