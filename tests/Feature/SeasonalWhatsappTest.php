<?php

declare(strict_types=1);

use App\Classes\Main\OpeningHours;
use App\Models\CatalogForm;
use App\Models\Company;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\SeasonalWindow;
use App\Models\User;
use App\Models\WhatsappVariant;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The landing's WhatsApp, by season
|--------------------------------------------------------------------------
| Same mechanism as the hero examples: the company row is the all-year
| answer and a variant overrides it field by field inside a window. The
| halves nobody checks by looking are that a variant inherits what it leaves
| blank, that the day the window closes the company comes back whole, and
| that "open now" is read in the company's own clock.
*/

/** Monday to Friday 09:00–18:00, the week most of these tests start from. */
const OFFICE_WEEK = [
    1 => ['09:00', '18:00'],
    2 => ['09:00', '18:00'],
    3 => ['09:00', '18:00'],
    4 => ['09:00', '18:00'],
    5 => ['09:00', '18:00'],
];

beforeEach(function (): void {
    app()->setLocale('es');

    // Caracas is UTC-4 all year, so "15:00 on a Wednesday" is not a DST guess.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 15:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function companyReach(array $attributes = []): Company
{
    $country = Country::factory()->create(['iso2' => 'VE', 'code' => 'VEN']);
    $province = Province::factory()->create(['country_id' => $country->id]);
    $region = Region::factory()->create(['province_id' => $province->id]);

    // No tax standing: its factory builds ANOTHER country, whose random iso2
    // can land on this one's VE and fail the suite by chance.
    return Company::factory()->create([
        'region_id' => $region->id,
        'tax_condition_id' => null,
        'phone' => '+54 9 11 5555-0000',
        'opening_schedule' => OFFICE_WEEK,
        'whatsapp_greeting' => 'Hola, quiero saber más.',
        ...$attributes,
    ]);
}

test('with no season running the landing reads the company row', function (): void {
    companyReach();

    expect(Company::reach())->toBe([
        'phone' => '5491155550000',
        'hours' => 'Lunes a viernes de 09:00 a 18:00 h',
        'open' => true,
        'greeting' => 'Hola, quiero saber más.',
        'season' => null,
    ]);
});

test('open now is read in the clock of the company country, not the server', function (): void {
    companyReach();

    // 21:00 in Caracas is after the close; the same instant is 01:00 UTC.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 21:00:00', 'America/Caracas'));
    expect(Company::reach()['open'])->toBeFalse();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 09:00:00', 'America/Caracas'));
    expect(Company::reach()['open'])->toBeTrue();

    // A Sunday is not in the week at all.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-18 12:00:00', 'America/Caracas'));
    expect(Company::reach()['open'])->toBeFalse();
});

test('a company that never declared a week is not reported closed', function (): void {
    companyReach(['opening_schedule' => null]);

    expect(Company::reach())->hours->toBeNull()->open->toBeNull();
});

test('a variant overrides what it fills and inherits the rest', function (): void {
    companyReach();
    $window = SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);

    WhatsappVariant::factory()->create([
        'seasonal_window_id' => $window->id,
        'opening_schedule' => [1 => ['10:00', '14:00']],
    ]);

    expect(Company::reach())
        ->hours->toBe('Lunes de 10:00 a 14:00 h')
        ->season->toBe('Navidad 2026')
        // Left blank by the variant, so it still comes from the company.
        ->phone->toBe('5491155550000')
        ->greeting->toBe('Hola, quiero saber más.');
});

test('a variant with an empty week closes the whole season, and null inherits', function (): void {
    companyReach();
    $window = SeasonalWindow::factory()->create();
    $variant = WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id, 'opening_schedule' => []]);

    expect(Company::reach())->hours->toBe('Cerrado')->open->toBeFalse();

    $variant->update(['opening_schedule' => null]);

    expect(Company::reach())->hours->toBe('Lunes a viernes de 09:00 a 18:00 h')->open->toBeTrue();
});

test('the day after a season ends the company row is back, whole', function (): void {
    companyReach();
    $window = SeasonalWindow::factory()->create(['ends_at' => SeasonalWindow::today()->addDay()]);

    WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id, 'phone' => '+54 9 11 4444-1111']);

    expect(Company::reach()['phone'])->toBe('5491144441111');

    $afterwards = Company::reach(SeasonalWindow::today()->addDays(2));

    expect($afterwards['phone'])->toBe('5491155550000')
        ->and($afterwards['season'])->toBeNull()
        // A preview of another day cannot say whether they answer right now.
        ->and($afterwards['open'])->toBeNull();
});

test('a variant switched off, or in a season switched off, never speaks', function (): void {
    companyReach();
    $off = SeasonalWindow::factory()->create(['is_active' => false]);

    WhatsappVariant::factory()->create(['seasonal_window_id' => $off->id, 'phone' => '+54 9 11 4444-1111']);
    WhatsappVariant::factory()->create(['phone' => '+54 9 11 3333-2222', 'is_active' => false]);

    expect(Company::reach()['phone'])->toBe('5491155550000');
});

test('the stronger season wins when two overlap', function (): void {
    companyReach();
    $weak = SeasonalWindow::factory()->create(['priority' => 1]);
    $strong = SeasonalWindow::factory()->create(['priority' => 9, 'name' => 'Fin de año']);

    WhatsappVariant::factory()->create(['seasonal_window_id' => $weak->id, 'phone' => '+54 9 11 1111-1111']);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $strong->id, 'phone' => '+54 9 11 2222-2222']);

    expect(Company::reach())->phone->toBe('5491122222222')->season->toBe('Fin de año');
});

test('saving a variant reaches the landing at once, not when the cache expires', function (): void {
    companyReach();
    $window = SeasonalWindow::factory()->create();

    // Prime today's cached answer, then edit.
    expect(Company::reach()['hours'])->toBe('Lunes a viernes de 09:00 a 18:00 h');

    WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id, 'opening_schedule' => []]);

    expect(Company::reach()['hours'])->toBe('Cerrado');
});

test('equal days fold into a run and a night shift keeps running past midnight', function (): void {
    $week = new OpeningHours([
        1 => ['09:00', '18:00'],
        2 => ['09:00', '18:00'],
        3 => ['09:00', '18:00'],
        6 => ['09:00', '13:00'],
        0 => ['09:00', '13:00'],
        5 => ['22:00', '02:00'],
    ], 'America/Caracas');

    expect($week->summary)->toBe(
        'Lunes a miércoles de 09:00 a 18:00 h · Viernes de 22:00 a 02:00 h · Sábado y domingo de 09:00 a 13:00 h',
    );

    // Friday 22:00 opens a shift that is still going at 01:00 on Saturday.
    $saturdayEarly = CarbonImmutable::parse('2026-10-17 01:00:00', 'America/Caracas');
    $saturdayNoon = CarbonImmutable::parse('2026-10-17 12:00:00', 'America/Caracas');

    expect($week->isOpenAt($saturdayEarly))->toBeTrue()
        ->and($week->isOpenAt($saturdayNoon))->toBeTrue()
        ->and($week->isOpenAt(CarbonImmutable::parse('2026-10-17 05:00:00', 'America/Caracas')))->toBeFalse();
});

test('the footer says whether they are answering now, and hides the dot with no week', function (): void {
    companyReach();

    $this->get('/')
        ->assertOk()
        ->assertSee('Lunes a viernes de 09:00 a 18:00 h')
        ->assertSee(__('landing.footer.open_now'));

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 21:00:00', 'America/Caracas'));
    Company::forgetReach();

    $this->get('/')->assertSee(__('landing.footer.closed_now'))->assertDontSee(__('landing.footer.open_now'));
});

test('the footer draws no dot when no week was ever declared', function (): void {
    companyReach(['opening_schedule' => null]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee(__('landing.footer.open_now'))
        ->assertDontSee(__('landing.footer.closed_now'));
});

test('the footer opens the chat with the season message', function (): void {
    companyReach();
    $window = SeasonalWindow::factory()->create();

    WhatsappVariant::factory()->create([
        'seasonal_window_id' => $window->id,
        'whatsapp_greeting' => 'Hola, ¿atienden en las fiestas?',
    ]);

    $this->get('/')->assertSee(rawurlencode('Hola, ¿atienden en las fiestas?'), false);
});

test('a season holds one variant only', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $window = SeasonalWindow::factory()->create();
    WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id]);

    Livewire::test('catalog.whatsapp-variant')
        ->call('openCreate')
        ->set('form.data.seasonal_window_id', $window->id)
        ->call('create')
        ->assertHasErrors();

    expect(WhatsappVariant::query()->count())->toBe(1);
});

test('the master is a row of the hub and saves a variant with its own week', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(CatalogForm::query()->where('component', 'catalog.whatsapp-variant')->exists())->toBeTrue();

    $window = SeasonalWindow::factory()->create();

    Livewire::test('catalog.whatsapp-variant')
        ->call('openCreate')
        ->set('form.data.seasonal_window_id', $window->id)
        ->set('form.data.override_schedule', true)
        ->set('form.data.opening_schedule.1.open', true)
        ->set('form.data.opening_schedule.1.opens_at', '10:00')
        ->set('form.data.opening_schedule.1.closes_at', '14:00')
        ->call('create')
        ->assertHasNoErrors();

    $variant = WhatsappVariant::query()->firstOrFail();

    expect($variant->seasonal_window_id)->toBe($window->id)
        ->and($variant->opening_schedule)->toBe(['1' => ['10:00', '14:00']])
        ->and($variant->phone)->toBeNull();
});

test('a half-typed hour names the box instead of closing the day', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $window = SeasonalWindow::factory()->create();

    Livewire::test('catalog.whatsapp-variant')
        ->call('openCreate')
        ->set('form.data.seasonal_window_id', $window->id)
        ->set('form.data.override_schedule', true)
        ->set('form.data.opening_schedule.1.open', true)
        ->set('form.data.opening_schedule.1.opens_at', '9')
        ->call('create')
        ->assertHasErrors(['opening_schedule.1.0']);

    expect(WhatsappVariant::query()->count())->toBe(0);
});

test('a variant with no opening message is flagged only when its season is about to start', function (): void {
    $soon = SeasonalWindow::factory()->create([
        'name' => 'Fin de año',
        'starts_at' => SeasonalWindow::today()->addDays(3),
        'ends_at' => SeasonalWindow::today()->addDays(10),
    ]);
    $far = SeasonalWindow::factory()->create([
        'starts_at' => SeasonalWindow::today()->addDays(20),
        'ends_at' => SeasonalWindow::today()->addDays(25),
    ]);
    $greeted = SeasonalWindow::factory()->create([
        'starts_at' => SeasonalWindow::today()->addDays(2),
        'ends_at' => SeasonalWindow::today()->addDays(5),
    ]);
    $running = SeasonalWindow::factory()->create(['starts_at' => SeasonalWindow::today()->subDay()]);

    WhatsappVariant::factory()->create(['seasonal_window_id' => $soon->id]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $far->id]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $greeted->id, 'whatsapp_greeting' => 'Hola']);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $running->id]);

    $pending = WhatsappVariant::awaitingGreeting();

    expect($pending)->toHaveCount(1)
        ->and($pending->first()['season'])->toBe('Fin de año')
        ->and($pending->first()['in_days'])->toBe(3);
});

test('the hub shows the reminder and drops it once the message is written', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $window = SeasonalWindow::factory()->create([
        'name' => 'Fin de año',
        'starts_at' => SeasonalWindow::today()->addDays(3),
        'ends_at' => SeasonalWindow::today()->addDays(10),
    ]);
    $variant = WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id]);

    Livewire::test('catalog.whatsapp-variant')
        ->assertSee(__('catalog.whatsapp_variant.reminder.title'))
        ->assertSee('Fin de año');

    $variant->update(['whatsapp_greeting' => 'Hola, estamos de fiesta.']);

    Livewire::test('catalog.whatsapp-variant')
        ->assertDontSee(__('catalog.whatsapp_variant.reminder.title'));
});

test('the week control draws Monday first and keeps a closed day closed', function (): void {
    $html = Blade::render(
        '<x-inputsform.week-hours name="opening_schedule" model="form.data.opening_schedule" :value="$value" label="Horario" />',
        ['value' => [1 => ['09:00', '18:00'], 6 => ['10:00', '13:00']]],
    );

    expect($html)
        ->toContain('Horario')
        ->toContain('form.data.opening_schedule.1.open')
        ->toContain('form.data.opening_schedule.1.opens_at')
        // Tuesday is off: its shift boxes are not drawn, and it says why.
        ->not->toContain('form.data.opening_schedule.2.opens_at')
        ->toContain('form.data.opening_schedule.6.closes_at')
        ->and(strpos($html, 'opening_schedule.1.open'))->toBeLessThan(strpos($html, 'opening_schedule.0.open'));
});
