<?php

declare(strict_types=1);

use App\Mail\HolidayBridgeNotice;
use App\Models\Business;
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
use Illuminate\Support\Facades\Mail;
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

test('copying brings the recurring days a country lacks and leaves one-year decrees behind', function (): void {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $argentina = holidayCountry('ARG');
    $chile = holidayCountry('CHL');

    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Día del Trabajador', 'month' => 5, 'day' => 1]);
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Viernes Santo', 'easter_offset' => -2]);
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Puente', 'on_date' => '2026-12-07']);
    // Chile already has the first of May under its own name: it is the same day, not a second row.
    CountryHoliday::query()->create(['country_id' => $chile->id, 'name' => 'Fiesta del Trabajo', 'month' => 5, 'day' => 1]);

    $panel = Livewire::test('catalog.country-holiday')
        ->call('openCopy')
        ->set('copy.source', (string) $argentina->id)
        ->set('copy.target', (string) $chile->id);

    // The preview is what would be written: the Easter day only (May 1st is taken, the decree never repeats).
    expect($panel->instance()->copyPreview->pluck('name')->all())->toBe(['Viernes Santo']);

    $panel->call('copyHolidays')
        ->assertSet('copying', false)
        ->assertDispatched('notify', type: 'success', message: 'Listo. Se copió 1 feriado.');

    $names = CountryHoliday::query()->where('country_id', $chile->id)->orderBy('name')->pluck('name')->all();

    expect($names)->toBe(['Fiesta del Trabajo', 'Viernes Santo']);
});

test('copying to the same country or with nothing missing writes nothing', function (): void {
    $argentina = holidayCountry('ARG');
    $chile = holidayCountry('CHL');
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Día del Trabajador', 'month' => 5, 'day' => 1]);
    CountryHoliday::query()->create(['country_id' => $chile->id, 'name' => 'Fiesta del Trabajo', 'month' => 5, 'day' => 1]);

    Livewire::test('catalog.country-holiday')
        ->call('openCopy')
        ->set('copy.source', (string) $argentina->id)
        ->set('copy.target', (string) $argentina->id)
        ->call('copyHolidays')
        ->assertHasErrors(['target']);

    Livewire::test('catalog.country-holiday')
        ->call('openCopy')
        ->set('copy.source', (string) $argentina->id)
        ->set('copy.target', (string) $chile->id)
        ->assertSee('ya tiene todos los feriados')
        ->assertDontSeeHtml('data-testid="holiday-copy-confirm"');

    expect(CountryHoliday::query()->count())->toBe(2);
});

test('the year view lands each shape on its date and flags a Tuesday or Thursday as a possible bridge', function (): void {
    $argentina = holidayCountry('ARG');

    // 2026-05-01 is a Friday; 2026-07-09 a Thursday; Good Friday 2026 is April 3.
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Día del Trabajador', 'month' => 5, 'day' => 1]);
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Día de la Independencia', 'month' => 7, 'day' => 9]);
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Viernes Santo', 'easter_offset' => -2]);
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Puente de otro año', 'on_date' => '2027-12-06']);

    $view = Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) $argentina->id)
        ->set('calendarYear', 2026)
        ->assertSee('Feriados de 2026')
        ->assertSee('3 feriados en 2026')
        ->assertSee('Puente posible');

    $days = fn () => $view->instance()->yearHolidays->map(fn (array $holiday): string => $holiday['date']->format('Y-m-d').' '.$holiday['name'])->all();

    expect($days())->toBe(['2026-04-03 Viernes Santo', '2026-05-01 Día del Trabajador', '2026-07-09 Día de la Independencia']);

    $view->call('shiftYear', 1)->assertSee('Feriados de 2027');

    expect($days())->toContain('2027-12-06 Puente de otro año');
});

test('a click on a day marks a one-year holiday, a second one switches it off and a third brings it back', function (): void {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    $argentina = holidayCountry('ARG');

    $view = Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) $argentina->id)
        ->set('calendarYear', 2026)
        ->call('toggleBridge', '2026-12-07')
        ->assertDispatched('notify', type: 'success', message: 'Listo. El 07/12/2026 quedó como feriado puente.');

    $day = fn () => CountryHoliday::query()->where('country_id', $argentina->id)->whereDate('on_date', '2026-12-07');

    expect($day()->count())->toBe(1)
        ->and($day()->value('name'))->toBe('Día puente')
        ->and($view->instance()->yearHolidays->pluck('kind')->all())->toBe(['once']);

    $view->call('toggleBridge', '2026-12-07')
        ->assertDispatched('notify', type: 'success', message: 'Listo. El feriado puente del 07/12/2026 dejó de contar.');

    // Kept, not deleted: it only stops counting.
    expect($day()->count())->toBe(1)
        ->and($day()->value('is_active'))->toBeFalse()
        ->and($view->instance()->yearHolidays)->toHaveCount(0);

    $view->call('toggleBridge', '2026-12-07');

    expect($day()->count())->toBe(1)->and($day()->value('is_active'))->toBeTrue();
});

test('a click with a date that does not exist or a country that is not one writes nothing', function (): void {
    $argentina = holidayCountry('ARG');

    Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) $argentina->id)
        ->call('toggleBridge', '2026-02-31')
        ->assertHasErrors(['date']);

    Livewire::test('catalog.country-holiday')
        ->set('calendarCountry', '99999')
        ->call('toggleBridge', '2026-12-07')
        ->assertHasErrors(['country']);

    expect(CountryHoliday::query()->count())->toBe(0);
});

test('the year view says so when the country has nothing loaded', function (): void {
    Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) holidayCountry('ARG')->id)
        ->assertSee('no tiene feriados vigentes');
});

test('a dragged range marks the free weekdays only and brings back a switched-off bridge', function (): void {
    $argentina = holidayCountry('ARG');

    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Decreto', 'on_date' => '2026-12-07', 'is_active' => true]);
    CountryHoliday::query()->create(['country_id' => $argentina->id, 'name' => 'Día puente', 'on_date' => '2026-12-04', 'is_active' => false]);

    Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) $argentina->id)
        ->call('bridgeRange', '2026-12-03', '2026-12-08')
        ->assertHasNoErrors()
        ->assertDispatched('notify', type: 'success', message: 'Listo. 3 días quedaron como feriado puente.');

    $on = fn (string $date) => CountryHoliday::query()->where('country_id', $argentina->id)->whereDate('on_date', $date);

    expect($on('2026-12-03')->value('is_active'))->toBeTrue()
        ->and($on('2026-12-04')->count())->toBe(1)
        ->and($on('2026-12-04')->value('is_active'))->toBeTrue()
        ->and($on('2026-12-05')->count())->toBe(0)
        ->and($on('2026-12-06')->count())->toBe(0)
        ->and($on('2026-12-07')->value('name'))->toBe('Decreto')
        ->and($on('2026-12-08')->count())->toBe(1);
});

test('a range that runs backwards, is too long or has a day that does not exist writes nothing', function (): void {
    $argentina = holidayCountry('ARG');
    $view = Livewire::test('catalog.country-holiday')->call('openCalendar')->set('calendarCountry', (string) $argentina->id);

    $view->call('bridgeRange', '2026-12-08', '2026-12-03')->assertHasErrors(['to']);
    $view->call('bridgeRange', '2026-01-01', '2026-03-01')->assertHasErrors(['to']);
    $view->call('bridgeRange', '2026-02-31', '2026-03-03')->assertHasErrors(['from']);

    expect(CountryHoliday::query()->count())->toBe(0);
});

test('marking a bridge offers the mail only when a business of the country can be reached, and sends it once accepted', function (): void {
    Mail::fake();

    $argentina = holidayCountry('ARG');
    $venezuela = holidayCountry('VEN');

    $reached = Business::factory()->create(['country_id' => $argentina->id, 'is_demo' => false]);
    $owner = User::factory()->create(['business_id' => $reached->id, 'email' => 'duena@kiosco.test']);

    // Not reachable: another country, one of ours (demo), and one with nobody to write to.
    $elsewhere = Business::factory()->create(['country_id' => $venezuela->id, 'is_demo' => false]);
    User::factory()->create(['business_id' => $elsewhere->id, 'email' => 'otra@kiosco.test']);
    $demo = Business::factory()->create(['country_id' => $argentina->id, 'is_demo' => true]);
    User::factory()->create(['business_id' => $demo->id, 'email' => 'demo@kiosco.test']);
    Business::factory()->create(['country_id' => $argentina->id, 'is_demo' => false]);

    $view = Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) $argentina->id)
        ->call('toggleBridge', '2026-12-07')
        ->assertReturned(fn (?array $offer): bool => $offer !== null
            && $offer['marked'] === ['2026-12-07']
            && $offer['title'] === '¿Avisar a 1 negocio de '.$argentina->name.'?');

    Mail::assertNothingQueued();

    // Switching it off is not news: no offer.
    $view->call('toggleBridge', '2026-12-07')->assertReturned(null);

    // A country nobody can be reached in: no offer either.
    Livewire::test('catalog.country-holiday')
        ->call('openCalendar')
        ->set('calendarCountry', (string) holidayCountry('CHL')->id)
        ->call('toggleBridge', '2026-12-07')
        ->assertReturned(null);

    $view->call('toggleBridge', '2026-12-07')
        ->call('noticeBridge', ['2026-12-07', '2026-12-25', 'not a date'])
        ->assertDispatched('notify', type: 'success', message: 'Listo. Se avisó a 1 negocio.');

    Mail::assertQueued(HolidayBridgeNotice::class, 1);
    Mail::assertQueued(HolidayBridgeNotice::class, fn (HolidayBridgeNotice $mail): bool => $mail->hasTo($owner->email) && $mail->dates === ['2026-12-07']);
});

test('the notice mails nothing for a day that is not an active bridge of the country', function (): void {
    Mail::fake();

    $argentina = holidayCountry('ARG');
    $business = Business::factory()->create(['country_id' => $argentina->id, 'is_demo' => false]);
    User::factory()->create(['business_id' => $business->id, 'email' => 'duena@kiosco.test']);

    Livewire::test('catalog.country-holiday')
        ->set('calendarCountry', (string) $argentina->id)
        ->call('noticeBridge', ['2026-12-07'])
        ->assertDispatched('notify', type: 'info', message: 'Ningún negocio para avisar.');

    Mail::assertNothingQueued();
});
