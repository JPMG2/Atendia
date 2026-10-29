<?php

declare(strict_types=1);

use App\Classes\Main\OpeningHours;
use App\Models\CatalogForm;
use App\Models\Company;
use App\Models\Country;
use App\Models\DemoTag;
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
use Illuminate\Support\Facades\Cache;
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
        'notice' => null,
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

test('the notice of a running season is READ in the footer, with the season that wrote it', function (): void {
    companyReach();
    SeasonalWindow::factory()->create(['name' => 'Navidad 2026', 'notice' => 'Atendemos el 24 hasta las 14 h']);

    $this->get('/')
        ->assertOk()
        ->assertSee(__('landing.footer.season', ['name' => 'Navidad 2026']))
        // On the page, not inside a chat: that was the whole complaint.
        ->assertSee('Atendemos el 24 hasta las 14 h')
        // The chat still opens with the company's own message, never with the notice.
        ->assertDontSee(rawurlencode('Atendemos el 24 hasta las 14 h'), false);
});

test('a season with no notice and no variant is not named, and a switched-off one does not speak', function (): void {
    companyReach();
    SeasonalWindow::factory()->create(['name' => 'Vacía']);
    SeasonalWindow::factory()->create(['name' => 'Apagada', 'notice' => 'No debería verse', 'is_active' => false]);

    $this->get('/')->assertOk()
        ->assertDontSee('Atención de Vacía')
        ->assertDontSee('Atención de Apagada')
        ->assertDontSee('No debería verse');
});

test('the strongest season with a notice wins, and the day it ends the notice is gone', function (): void {
    companyReach();
    SeasonalWindow::factory()->create(['name' => 'Débil', 'priority' => 1, 'notice' => 'Aviso débil']);
    SeasonalWindow::factory()->create(['name' => 'Fuerte', 'priority' => 9, 'notice' => 'Aviso fuerte', 'ends_at' => SeasonalWindow::today()->addDay()]);

    expect(Company::reach())->notice->toBe('Aviso fuerte')->season->toBe('Fuerte');

    // Two days on, Fuerte has ended and the weaker one is the only one talking.
    expect(Company::reach(SeasonalWindow::today()->addDays(2)))->notice->toBe('Aviso débil');
    expect(Company::reach(SeasonalWindow::today()->addDays(30))['notice'])->toBeNull();
});

test('a variant with hours but no notice still names its season', function (): void {
    companyReach();
    $window = SeasonalWindow::factory()->create(['name' => 'Vacaciones']);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id, 'opening_schedule' => []]);

    expect(Company::reach())->season->toBe('Vacaciones')->notice->toBeNull();
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

test('the notice is written in the season form itself, with its dates, in one place', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test('catalog.seasonal-window')
        ->call('openCreate')
        ->set('form.data.name', 'Navidad 2026')
        ->set('form.data.range', SeasonalWindow::today()->toDateString().'..'.SeasonalWindow::today()->addDays(10)->toDateString())
        ->set('form.data.notice', 'Atendemos el 24 hasta las 14 h')
        ->call('create')
        ->assertHasNoErrors();

    expect(SeasonalWindow::query()->where('name', 'Navidad 2026')->value('notice'))->toBe('Atendemos el 24 hasta las 14 h')
        // And it is the landing that reads it, with nothing else configured.
        ->and(Company::reach()['notice'])->toBe('Atendemos el 24 hasta las 14 h');
});

test('the season list says what each season changes, and a season with nothing says so', function (): void {
    $empty = SeasonalWindow::factory()->create(['name' => 'Vacía']);
    $full = SeasonalWindow::factory()->create(['name' => 'Con cosas']);

    $tag = DemoTag::factory()->create(['slug' => 'kiosco']);
    DemoTag::factory()->variantOf($tag, $full)->create();
    WhatsappVariant::factory()->create(['seasonal_window_id' => $full->id]);
    // A variant switched off changes nothing, so it does not count as content.
    WhatsappVariant::factory()->create(['is_active' => false]);

    $noticed = SeasonalWindow::factory()->create(['name' => 'Con aviso', 'notice' => 'Cerramos el 25']);

    $rows = (new SeasonalWindow)->catalogRows()->keyBy('name');

    expect($rows['Con aviso']['has_notice'])->toBeTrue()->and($rows['Vacía']['has_notice'])->toBeFalse()
        ->and($noticed->id)->not->toBe($empty->id)
        ->and($rows['Vacía']['hero'])->toBe(0)->and($rows['Vacía']['whatsapp'])->toBe(0)
        ->and($rows['Con cosas']['hero'])->toBe(1)->and($rows['Con cosas']['whatsapp'])->toBe(1)
        ->and($empty->id)->not->toBe($full->id);
});

test('a season runs in the list on its first and last day, as on the landing', function (): void {
    $today = SeasonalWindow::today();
    $ending = SeasonalWindow::factory()->create(['name' => 'Termina hoy', 'is_active' => true, 'starts_at' => $today->subDays(3), 'ends_at' => $today]);
    $starting = SeasonalWindow::factory()->create(['name' => 'Empieza hoy', 'is_active' => true, 'starts_at' => $today, 'ends_at' => $today->addDays(3)]);
    SeasonalWindow::factory()->create(['name' => 'Terminó ayer', 'is_active' => true, 'starts_at' => $today->subDays(3), 'ends_at' => $today->subDay()]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $ending->id, 'is_active' => true]);

    $rows = (new SeasonalWindow)->catalogRows()->keyBy('name');

    expect($rows['Termina hoy']['running'])->toBeTrue()
        ->and($rows['Empieza hoy']['running'])->toBeTrue()
        ->and($rows['Terminó ayer']['running'])->toBeFalse()
        ->and(SeasonalWindow::inForce()->pluck('id')->sort()->values()->all())->toBe(collect([$ending->id, $starting->id])->sort()->values()->all())
        ->and((new WhatsappVariant)->catalogRows()->first()['running'])->toBeTrue();
});

test('the season list opens with the season the landing speaks with today, and says when none does', function (): void {
    $today = SeasonalWindow::today();
    $line = fn (): array => (new SeasonalWindow)->catalogHighlights()[0];

    expect($line())->toBe(['when' => __('catalog.seasonal_window.highlights.none_when'), 'text' => __('catalog.seasonal_window.highlights.none'), 'mono' => false]);

    // A switched-off season inside its dates is not the one speaking.
    SeasonalWindow::factory()->create(['name' => 'Apagada', 'is_active' => false, 'priority' => 9, 'starts_at' => $today->subDay(), 'ends_at' => $today]);
    $weak = SeasonalWindow::factory()->create(['name' => 'Débil', 'is_active' => true, 'priority' => 1, 'starts_at' => $today->subDay(), 'ends_at' => $today->addDay()]);
    expect($line()['text'])->toBe(trans_choice('catalog.seasonal_window.highlights.ends', 1, ['count' => 1, 'date' => $today->addDay()->format('d/m/Y')]));

    // Two in force: the strongest is the one the landing shows, and its last day is today.
    SeasonalWindow::factory()->create(['name' => 'Fuerte', 'is_active' => true, 'priority' => 5, 'starts_at' => $today->subDays(2), 'ends_at' => $today]);
    expect($line())->toBe(['when' => 'Fuerte', 'text' => trans_choice('catalog.seasonal_window.highlights.ends', 0, ['count' => 0, 'date' => $today->format('d/m/Y')]), 'mono' => false])
        ->and($line()['when'])->not->toBe($weak->name);
});

test('a grey pill says why it is off: the switch, the season\'s switch, or the dates', function (): void {
    $today = SeasonalWindow::today();
    $offInside = SeasonalWindow::factory()->create(['name' => 'Apagada dentro', 'is_active' => false, 'starts_at' => $today->subDay(), 'ends_at' => $today->addDay()]);
    $onOutside = SeasonalWindow::factory()->create(['name' => 'Prendida fuera', 'is_active' => true, 'starts_at' => $today->addDays(5), 'ends_at' => $today->addDays(9)]);
    $running = SeasonalWindow::factory()->create(['name' => 'En curso', 'is_active' => true, 'starts_at' => $today, 'ends_at' => $today->addDay()]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $offInside->id, 'is_active' => true]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $onOutside->id, 'is_active' => true]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $running->id, 'is_active' => false]);

    $windows = (new SeasonalWindow)->catalogRows()->keyBy('name');
    $variants = (new WhatsappVariant)->catalogRows()->keyBy('season');

    expect($windows['Apagada dentro']['running_shown'])->toBe(__('catalog.seasonal_window.status.off'))
        ->and($windows['Prendida fuera']['running_shown'])->toBe(__('catalog.seasonal_window.status.waiting'))
        ->and($windows['En curso']['running_shown'])->toBe(__('catalog.seasonal_window.status.running'))
        ->and($variants['Apagada dentro']['running_shown'])->toBe(__('catalog.whatsapp_variant.status.season_off'))
        ->and($variants['Prendida fuera']['running_shown'])->toBe(__('catalog.whatsapp_variant.status.waiting'))
        ->and($variants['En curso']['running_shown'])->toBe(__('catalog.whatsapp_variant.status.off'));

    WhatsappVariant::query()->update(['is_active' => true]);
    expect((new WhatsappVariant)->catalogRows()->keyBy('season')['En curso']['running_shown'])->toBe(__('catalog.whatsapp_variant.status.running'));

    // Two seasons in force: only the stronger one's variant is what the landing reads; the other says who wins.
    $stronger = SeasonalWindow::factory()->create(['name' => 'Más fuerte', 'is_active' => true, 'priority' => 9, 'starts_at' => $today, 'ends_at' => $today->addDay()]);
    $winner = WhatsappVariant::factory()->create(['seasonal_window_id' => $stronger->id, 'phone' => '+58-0414-9990000', 'is_active' => true]);
    Cache::flush();
    $variants = (new WhatsappVariant)->catalogRows()->keyBy('season');

    expect($variants['Más fuerte']['running'])->toBeTrue()
        ->and($variants['En curso']['running'])->toBeFalse()
        ->and($variants['En curso']['running_shown'])->toBe(__('catalog.whatsapp_variant.status.outranked'))
        ->and(Company::reach()['phone'])->toBe(preg_replace('/\D+/', '', $winner->phone));
});

test('with no season speaking today, the list says which one comes next and when, never a switched-off one', function (): void {
    $today = SeasonalWindow::today();
    SeasonalWindow::factory()->create(['name' => 'Apagada', 'is_active' => false, 'starts_at' => $today->addDay(), 'ends_at' => $today->addDays(3)]);
    SeasonalWindow::factory()->create(['name' => 'Lejana', 'is_active' => true, 'starts_at' => $today->addDays(40), 'ends_at' => $today->addDays(45)]);
    SeasonalWindow::factory()->create(['name' => 'Pasada', 'is_active' => true, 'starts_at' => $today->subDays(9), 'ends_at' => $today->subDay()]);
    $soon = fn (): array => (new SeasonalWindow)->catalogHighlights();

    expect($soon())->toBe([
        ['when' => __('catalog.seasonal_window.highlights.none_when'), 'text' => __('catalog.seasonal_window.highlights.none'), 'mono' => false],
        ['when' => 'Lejana', 'text' => trans_choice('catalog.seasonal_window.highlights.starts', 40, ['count' => 40, 'date' => $today->addDays(40)->format('d/m/Y')]), 'mono' => false],
    ]);

    SeasonalWindow::factory()->create(['name' => 'Mañana', 'is_active' => true, 'starts_at' => $today->addDay(), 'ends_at' => $today->addDays(2)]);
    expect($soon()[1])->toBe(['when' => 'Mañana', 'text' => trans_choice('catalog.seasonal_window.highlights.starts', 1, ['count' => 1, 'date' => $today->addDay()->format('d/m/Y')]), 'mono' => false])
        ->and($soon())->toHaveCount(2);
});

test('a variant that inherits says what it inherits from the company: the number and the week, and only those', function (): void {
    Company::factory()->create(['phone' => '+58-0412-5550199', 'opening_schedule' => [1 => ['09:00', '18:00']]]);
    $both = SeasonalWindow::factory()->create(['name' => 'Navidad', 'starts_at' => '2026-12-01']);
    $own = SeasonalWindow::factory()->create(['name' => 'Carnaval', 'starts_at' => '2026-02-01']);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $both->id, 'phone' => null, 'opening_schedule' => null]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $own->id, 'phone' => '+58-0414-1112233', 'opening_schedule' => null]);
    $hours = (new OpeningHours(OpeningHours::normalize(Company::current()->opening_schedule), Company::localTimezone()))->summary;
    $rows = (new WhatsappVariant)->catalogRows()->keyBy('season');
    $phone = __('catalog.whatsapp_variant.inherits.phone', ['value' => '+58-0412-5550199']);
    $week = __('catalog.whatsapp_variant.inherits.hours', ['value' => $hours]);

    expect($rows['Navidad'])->toMatchArray([
        'inherits_note' => __('catalog.whatsapp_variant.inherits.note', ['parts' => $phone.' · '.$week]),
        'phone_shown' => __('catalog.whatsapp_variant.status.inherits'),
        'hours_inherits' => true,
    ])
        ->and($rows['Carnaval']['inherits_note'])->toBe(__('catalog.whatsapp_variant.inherits.note', ['parts' => $week]))
        ->and($rows['Navidad']['company_url'])->toBe(route('admin.company'))
        ->and($hours)->not->toBe('');

    // A row that inherits nothing names nothing, and carries no door to the company screen.
    WhatsappVariant::query()->update(['phone' => '+58-0414-1112233', 'opening_schedule' => []]);
    expect((new WhatsappVariant)->catalogRows()->pluck('company_url')->filter()->all())->toBe([])
        ->and((new WhatsappVariant)->catalogRows()->pluck('inherits_note')->unique()->all())->toBe(['']);
});

test('Monday is written onto Tuesday to Friday and the weekend is left alone', function (): void {
    $week = OpeningHours::applyWeekdays([
        1 => ['open' => true, 'opens_at' => '10:00', 'closes_at' => '14:00'],
        3 => ['open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
        6 => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '12:00'],
    ]);

    expect(OpeningHours::toStored($week))->toBe([
        1 => ['10:00', '14:00'],
        2 => ['10:00', '14:00'],
        3 => ['10:00', '14:00'],
        4 => ['10:00', '14:00'],
        5 => ['10:00', '14:00'],
        6 => ['09:00', '12:00'],
    ]);
});

test('the company screen and the season form both copy Monday with the button', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    companyReach(['opening_schedule' => [1 => ['09:00', '13:00']]]);

    Livewire::test('admin.company.index')
        ->call('applyWeekdays')
        ->assertSet('form.data.opening_schedule.4.open', true)
        ->assertSet('form.data.opening_schedule.4.closes_at', '13:00')
        ->assertSet('form.data.opening_schedule.6.open', false)
        ->call('applySaturday')
        ->assertSet('form.data.opening_schedule.6.open', true)
        ->assertSet('form.data.opening_schedule.6.closes_at', '13:00')
        ->assertSet('form.data.opening_schedule.0.open', false);

    Livewire::test('catalog.whatsapp-variant')
        ->call('openCreate')
        ->set('form.data.override_schedule', true)
        ->set('form.data.opening_schedule.1.open', true)
        ->set('form.data.opening_schedule.1.opens_at', '11:00')
        ->set('form.data.opening_schedule.1.closes_at', '15:00')
        ->call('applyWeekdays')
        ->assertSet('form.data.opening_schedule.5.opens_at', '11:00');
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
