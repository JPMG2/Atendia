<?php

declare(strict_types=1);

use App\Actions\Billing\FetchExchangeRate;
use App\Actions\Billing\SubmitPaymentReceipt;
use App\Classes\Main\ExchangeRateDesk;
use App\Dto\SensorReadingDto;
use App\Enums\ExchangeRateSource;
use App\Enums\PlatformSensorStatus;
use App\Models\Business;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\User;
use App\Services\ExchangeRates\BcvPage;
use App\Services\ExchangeRates\DolarApi;
use App\Services\PlatformSensors;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The bolivar rate (D-02)
|--------------------------------------------------------------------------
| Her chain: the BCV page charges, the official number from dolarapi backs it
| up, and she types it when both fail. The parallel is shown, never charged.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // The app clock stays on UTC on purpose: the value date must be Caracas's anyway.
    // A Wednesday morning: every business-day count below starts from here.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'America/Caracas'));
    Storage::fake('local');
});

/** The dollar box as the BCV home page printed it on 2026-11-02. */
function bcvHtml(string $rate = '889,57540000', string $date = '2026-11-04T00:00:00-04:00'): string
{
    return <<<HTML
        <div id="euro"><strong class="strong-tb">1.034,12000000</strong></div>
        <div id="dolar" class="col-sm-12 col-xs-12 ">
            <div class="field-content"><div class="row recuadrotsmc">
                <div class="col-sm-6 col-xs-6"><img src="/sites/default/files/dollar-04_2.png"> <span> USD</span></div>
                <div class="col-sm-6 col-xs-6 centrado textp"> <strong class="strong-tb">{$rate}</strong>  </div>
            </div></div>
        </div>
        <div class="pull-right dinpro center">
        Fecha Valor: <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="{$date}">Miércoles, 04 Noviembre  2026</span>
        </div>
        HTML;
}

/** @return list<array<string, mixed>> */
function dolarApiRows(float $official = 889.5754, ?float $parallel = 993.832815, string $date = '2026-11-04T00:00:00-04:00'): array
{
    return [
        ['moneda' => 'USD', 'fuente' => 'oficial', 'nombre' => 'Dólar', 'compra' => null, 'venta' => null, 'promedio' => $official, 'fechaActualizacion' => $date],
        ['moneda' => 'USD', 'fuente' => 'paralelo', 'nombre' => 'Paralelo', 'compra' => null, 'venta' => null, 'promedio' => $parallel, 'fechaActualizacion' => '2026-11-04T04:01:52.686Z'],
    ];
}

function fakeSources(?string $bcv, ?array $api): void
{
    Http::fake([
        BcvPage::URL => $bcv === null ? Http::response('Service Unavailable', 503) : Http::response($bcv),
        DolarApi::URL => $api === null ? Http::response('', 500) : Http::response($api),
    ]);
}

function rateSensor(): SensorReadingDto
{
    return collect(app(PlatformSensors::class)->read())->firstWhere('key', 'exchange_rate');
}

function rateAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    test()->seed(MenuSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin->refresh();
}

function bolivarBusiness(): Business
{
    $ves = Currency::factory()->create(['code' => 'VES', 'symbol' => 'Bs.']);

    return Business::factory()->create(['country_id' => Country::factory()->create(['currency_id' => $ves->id])->id, 'timezone' => 'America/Caracas']);
}

test('the BCV page is read from its dollar box and its value date', function (): void {
    $reading = BcvPage::parse(bcvHtml('1.234,56780000'));

    expect($reading->official)->toBe(1234.5678)
        ->and($reading->rateDate->toDateString())->toBe('2026-11-04')
        ->and($reading->source)->toBe(ExchangeRateSource::BcvPage);
});

test('a BCV page that changed its design throws instead of guessing', function (): void {
    BcvPage::parse('<div id="dolar"><span>889,57</span></div>');
})->throws(RuntimeException::class);

test('dolarapi gives the official rate and the parallel apart', function (): void {
    $reading = DolarApi::parse(dolarApiRows());

    expect($reading->official)->toBe(889.5754)
        ->and($reading->parallel)->toBe(993.832815)
        ->and($reading->rateDate->toDateString())->toBe('2026-11-04');
});

test('the BCV page charges and the parallel is kept beside it', function (): void {
    fakeSources(bcvHtml(), dolarApiRows(official: 880.0));

    app(FetchExchangeRate::class)->handle();
    $rate = ExchangeRate::charging();

    expect($rate->source)->toBe(ExchangeRateSource::BcvPage)
        ->and((float) $rate->official)->toBe(889.5754)
        ->and((float) $rate->parallel)->toBe(993.8328);
});

test('when the BCV page fails the official number of dolarapi charges, never the parallel', function (): void {
    fakeSources(null, dolarApiRows());

    $check = app(FetchExchangeRate::class)->handle();
    $rate = ExchangeRate::charging();

    expect($check['bcv'])->not->toBeNull()
        ->and($rate->source)->toBe(ExchangeRateSource::DolarApi)
        ->and((float) $rate->official)->toBe(889.5754)
        ->and(rateSensor()->status)->toBe(PlatformSensorStatus::Warn);
});

test('when both fail nothing is written and the last rate keeps charging', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 885]);
    fakeSources(null, null);

    app(FetchExchangeRate::class)->handle();

    expect(ExchangeRate::count())->toBe(1)
        ->and((float) ExchangeRate::charging()->official)->toBe(885.0)
        ->and(rateSensor()->status)->toBe(PlatformSensorStatus::Warn)
        ->and(rateSensor()->detail)->toContain('03/11');
});

test('tomorrow\'s rate published today does not charge before its date', function (): void {
    ExchangeRate::factory()->on('2026-11-04')->create(['official' => 889.5754]);
    fakeSources(bcvHtml('891,00000000', '2026-11-05T00:00:00-04:00'), dolarApiRows(date: '2026-11-05T00:00:00-04:00'));

    app(FetchExchangeRate::class)->handle();

    expect((float) ExchangeRate::charging()->official)->toBe(889.5754)
        ->and(ExchangeRate::newest()->rate_date->toDateString())->toBe('2026-11-05');
});

test('a jump bigger than she allows waits for her and does not charge', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 800]);
    fakeSources(bcvHtml('900,00000000'), dolarApiRows(official: 900));

    app(FetchExchangeRate::class)->handle();

    expect(ExchangeRate::awaitingConfirmation()?->rate_date->toDateString())->toBe('2026-11-04')
        ->and((float) ExchangeRate::charging()->official)->toBe(800.0)
        ->and(rateSensor()->status)->toBe(PlatformSensorStatus::Warn);

    config()->set('atendia.exchange_rate.max_jump_percent', 15);
    app(FetchExchangeRate::class)->handle();

    expect(ExchangeRate::awaitingConfirmation())->toBeNull()
        ->and((float) ExchangeRate::charging()->official)->toBe(900.0);
});

test('what she typed for a date is not replaced by an automatic value of the same date', function (): void {
    ExchangeRate::factory()->manual()->on('2026-11-04')->create(['official' => 880]);
    fakeSources(bcvHtml(), dolarApiRows());

    app(FetchExchangeRate::class)->handle();
    $rate = ExchangeRate::charging();

    expect($rate->source)->toBe(ExchangeRateSource::Manual)
        ->and((float) $rate->official)->toBe(880.0)
        ->and((float) $rate->parallel)->toBe(993.8328);
});

test('a rate is old after the business days she allows, and a Friday rate on Monday is not', function (): void {
    ExchangeRate::factory()->on('2026-10-30')->create();
    expect(rateSensor()->status)->toBe(PlatformSensorStatus::Crit);

    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'America/Caracas'));
    expect(ExchangeRate::charging()->isStale(2))->toBeFalse();
});

test('the screen is the admin\'s: a client is turned away', function (): void {
    rateAdmin();
    $client = User::factory()->create(['business_id' => Business::factory()->create()->id]);
    $client->syncRoles('client');

    $this->actingAs($client->refresh())->get(route('admin.exchange-rate'))->assertForbidden();
});

test('the screen shows the rate that charges, its source and the parallel as reference', function (): void {
    $this->seed(PlanSeeder::class);
    ExchangeRate::factory()->create();
    $this->actingAs(rateAdmin())
        ->get(route('admin.exchange-rate'))
        ->assertSuccessful()
        ->assertSee('889,5754')
        ->assertSee('993,8328')
        ->assertSee(__('exchange_rate.sources.bcv_page'))
        ->assertSee(__('exchange_rate.tiles.parallel_note'));
});

test('typing a rate by hand needs its own key, not only the door of the screen', function (): void {
    rateAdmin();
    $role = Role::findOrCreate('cobranza', 'web');
    $role->syncPermissions(['access-admin-panel', 'payments.view']);
    $staff = User::factory()->create();
    $staff->syncRoles('cobranza');

    $this->actingAs($staff->refresh());
    livewire('admin.exchange-rate.index')
        ->assertSee(__('exchange_rate.manual.no_permission'))
        ->set('form.rate', '890,1234')
        ->call('save')
        ->assertForbidden();

    expect(ExchangeRate::count())->toBe(0);
});

test('she types a rate and it charges at once, with her name on it', function (): void {
    $admin = rateAdmin();
    $this->actingAs($admin);

    livewire('admin.exchange-rate.index')
        ->set('form.rate', '890,1234')
        ->set('form.rate_date', '2026-11-04')
        ->call('save')
        ->assertHasNoErrors();

    $rate = ExchangeRate::charging();
    expect((float) $rate->official)->toBe(890.1234)
        ->and($rate->source)->toBe(ExchangeRateSource::Manual)
        ->and($rate->entered_by)->toBe($admin->id);
});

test('a typed rate far from the one charging is flagged before saving', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 800]);
    $this->actingAs(rateAdmin());

    livewire('admin.exchange-rate.index')
        ->set('form.rate', '1.000,00')
        ->call('jumpBeyondRange')
        ->assertReturned(['percent' => '25,0', 'rate' => '1.000,0000']);
});

test('she confirms a waiting jump and from then on it charges', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 800]);
    $waiting = ExchangeRate::factory()->awaitingConfirmation()->create(['official' => 900]);
    $this->actingAs(rateAdmin());

    livewire('admin.exchange-rate.index')->call('confirmWaiting', $waiting->id, '900,0000');

    expect((float) ExchangeRate::charging()->official)->toBe(900.0);
});

test('a payment keeps the rate in force when the receipt was sent', function (): void {
    $this->seed(PlanSeeder::class);
    ExchangeRate::factory()->create(['official' => 889.5754]);
    $business = bolivarBusiness();

    $payment = app(SubmitPaymentReceipt::class)->handle($business, UploadedFile::fake()->image('comprobante.jpg'), '0001');
    ExchangeRate::factory()->on('2026-11-05')->create(['official' => 999]);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00', 'America/Caracas'));

    expect((float) $payment->refresh()->exchange_rate)->toBe(889.5754)
        ->and($payment->exchange_rate_on->toDateString())->toBe('2026-11-04');
});

test('mis pagos shows dollars first and offers bolivars only to a business that lives in them', function (): void {
    $this->seed(PlanSeeder::class);
    ExchangeRate::factory()->create(['official' => 900]);
    $business = bolivarBusiness();
    Payment::factory()->create(['business_id' => $business->id, 'amount' => 79, 'currency' => 'USD', 'exchange_rate' => 900, 'exchange_rate_on' => '2026-11-04']);
    Payment::factory()->create(['business_id' => $business->id, 'amount' => 29, 'currency' => 'USD']);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    livewire('payments.index')
        ->assertSee(__('exchange_rate.toggle.label'))
        ->assertSee('USD 79,00')
        ->assertSee('Bs 71.100,00')
        ->assertSee(__('exchange_rate.toggle.no_rate'))
        ->assertSeeHtml('x-data="{ ves: false }"');

    $elsewhere = Business::factory()->create(['country_id' => Country::factory()->create()->id]);
    $this->actingAs(User::factory()->create(['business_id' => $elsewhere->id])->refresh());

    livewire('payments.index')->assertDontSee(__('exchange_rate.toggle.label'));
});

test('the admin payments desk shows each payment in bolivars at the rate it kept', function (): void {
    $business = bolivarBusiness();
    Payment::factory()->create(['business_id' => $business->id, 'amount' => 79, 'currency' => 'USD', 'exchange_rate' => 889.5754, 'exchange_rate_on' => '2026-11-04']);
    $this->actingAs(rateAdmin());

    livewire('admin.payments.index')
        ->assertSee(__('exchange_rate.toggle.label'))
        ->assertSee('Bs 70.276,46')
        ->assertSee(__('exchange_rate.toggle.paid_at', ['date' => '04/11/2026']));
});

test('the money toggle draws dollars pressed and remembers per screen', function (): void {
    $html = Blade::render('<div x-data="{ ves: false }"><x-ui.money-toggle remember="my-payments" /></div>');

    expect($html)->toContain("moneyToggle('my-payments')")
        ->toContain('x-on:click="pick(true)"')
        ->toContain('x-on:click="pick(false)"')
        ->toContain(__('exchange_rate.toggle.usd'))
        ->toContain(__('exchange_rate.toggle.ves'));
});

test('the receipt panel says the exact bolivars to transfer at today\'s official rate', function (): void {
    $this->seed(PlanSeeder::class);
    ExchangeRate::factory()->create(['official' => 889.5754]);
    $business = bolivarBusiness();
    $business->subscription->forceFill(['plan' => 'negocio', 'billing_cycle' => 'monthly', 'current_period_ends_at' => now()->addDays(10)])->save();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    livewire('payments.index')
        ->call('openUpload')
        ->assertSee(__('exchange_rate.receipt.title'))
        ->assertSeeHtml('Bs 70.276,46')
        ->assertSee('04/11/2026');
});

test('the screen draws the month of each rate with where it started and where it is', function (): void {
    $this->seed(PlanSeeder::class);
    ExchangeRate::factory()->on('2026-10-20')->create(['official' => 800, 'parallel' => 900]);
    ExchangeRate::factory()->on('2026-11-04')->create(['official' => 880, 'parallel' => 990]);
    ExchangeRate::factory()->on('2026-09-01')->create(['official' => 500]);
    $this->actingAs(rateAdmin());

    livewire('admin.exchange-rate.index')
        ->assertSee('30 días: 800,0000 → 880,0000 (+10,0 %)')
        ->assertSee('30 días: 900,0000 → 990,0000 (+10,0 %)')
        ->assertSeeHtml('aria-label="'.__('exchange_rate.trend.official').'"');
});

test('a rate line drawn from its own low keeps a 1 % move visible', function (): void {
    $zero = Blade::render('<x-ui.sparkline :values="[880, 889]" />');
    $min = Blade::render('<x-ui.sparkline :values="[880, 889]" baseline="min" />');

    expect($zero)->toContain('points="0,1.2 104,1"')
        ->and($min)->toContain('points="0,19 104,1"');
});

test('the landing in Venezuela grounds the featured plan in the stored rate', function (): void {
    $this->seed(PlanSeeder::class);
    ExchangeRate::factory()->create(['official' => 889.5754]);

    app()->setLocale('es_VE');
    $html = Blade::render('<x-site.pricing />');

    expect($html)->toContain('Bs. 70.276');
});

test('an absurd value on the BCV page hands the turn to dolarapi instead of waiting', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 889]);
    fakeSources(bcvHtml('8.900.000,00000000'), dolarApiRows(official: 890));

    $check = app(FetchExchangeRate::class)->handle();
    $rate = ExchangeRate::charging();

    expect($rate->source)->toBe(ExchangeRateSource::DolarApi)
        ->and((float) $rate->official)->toBe(890.0)
        ->and($check['bcv'])->toContain('out of range')
        ->and(ExchangeRate::awaitingConfirmation())->toBeNull();
});

test('a jump she confirmed survives a last-decimal correction of the same day', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 800]);
    ExchangeRate::factory()->create(['official' => 900, 'confirmed_at' => now()]);
    fakeSources(null, dolarApiRows(official: 900.01));

    app(FetchExchangeRate::class)->handle();

    expect((float) ExchangeRate::charging()->official)->toBe(900.01)
        ->and(ExchangeRate::awaitingConfirmation())->toBeNull();
});

test('a waiting value overtaken by a newer value date no longer asks for her', function (): void {
    ExchangeRate::factory()->on('2026-11-02')->create(['official' => 800]);
    ExchangeRate::factory()->on('2026-11-03')->awaitingConfirmation()->create(['official' => 8895754]);
    ExchangeRate::factory()->on('2026-11-04')->create(['official' => 805]);

    expect(ExchangeRate::awaitingConfirmation())->toBeNull()
        ->and(rateSensor()->status)->toBe(PlatformSensorStatus::Ok);
});

test('with her typed rate charging, the screen and the board name it as hers', function (): void {
    ExchangeRate::factory()->manual()->create(['official' => 880]);
    fakeSources(null, dolarApiRows());
    app(FetchExchangeRate::class)->handle();
    $this->actingAs(rateAdmin());

    expect((new ExchangeRateDesk)->state)->toBe('manual')
        ->and(rateSensor()->detail)->toContain(__('exchange_rate.sources.manual'));

    livewire('admin.exchange-rate.index')
        ->assertSee(__('exchange_rate.alerts.manual_title'))
        ->assertDontSee(__('exchange_rate.alerts.fallback_title'));
});

test('tomorrow\'s rate does not charge at 21:00 in Caracas even with the platform clock on UTC', function (): void {
    ExchangeRate::factory()->on('2026-11-04')->create(['official' => 800]);
    ExchangeRate::factory()->on('2026-11-05')->create(['official' => 820]);
    $this->travelTo(CarbonImmutable::parse('2026-11-04 21:00', 'America/Caracas'));

    expect(config('app.timezone'))->toBe('UTC')
        ->and((float) ExchangeRate::charging()->official)->toBe(800.0);
});

test('a rate typed the Venezuelan way reads its dot as thousands', function (string $typed, float $stored): void {
    $this->actingAs(rateAdmin());

    livewire('admin.exchange-rate.index')
        ->set('form.rate', $typed)
        ->set('form.rate_date', '2026-11-04')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) ExchangeRate::charging()->official)->toBe($stored);
})->with([
    ['1.050', 1050.0],
    ['1.050,25', 1050.25],
    ['889,5754', 889.5754],
    ['889.5754', 889.5754],
]);

test('a typed rate for another day says when it really charges', function (string $date, string $key): void {
    ExchangeRate::factory()->create(['official' => 889]);
    $this->actingAs(rateAdmin());

    livewire('admin.exchange-rate.index')
        ->set('form.rate', '890,00')
        ->set('form.rate_date', $date)
        ->call('save')
        ->assertDispatched('notify', fn (string $name, array $params): bool => ($params['message'] ?? null) === __($key, ['date' => CarbonImmutable::parse($date)->format('d/m/Y')]));

    expect((float) ExchangeRate::charging()->official)->toBe(889.0);
})->with([
    ['2026-11-06', 'exchange_rate.manual.saved_future'],
    ['2026-11-01', 'exchange_rate.manual.saved_past'],
]);

test('an old rate with a jump waiting points at the jump, not at typing one', function (): void {
    ExchangeRate::factory()->on('2026-10-30')->create(['official' => 800]);
    ExchangeRate::factory()->on('2026-11-03')->awaitingConfirmation()->create(['official' => 900]);

    expect(rateSensor()->status)->toBe(PlatformSensorStatus::Crit)
        ->and(rateSensor()->detail)->toBe(__('platform_events.detail.rate_jump', ['percent' => '12,5']));

    $this->actingAs(rateAdmin());
    livewire('admin.exchange-rate.index')->assertSee(__('exchange_rate.alerts.down_waiting_body', ['date' => '30/10/2026']));
});

test('a confirmation for a value that changed after it was shown is refused', function (): void {
    ExchangeRate::factory()->on('2026-11-03')->create(['official' => 800]);
    $waiting = ExchangeRate::factory()->awaitingConfirmation()->create(['official' => 905]);
    $this->actingAs(rateAdmin());

    livewire('admin.exchange-rate.index')->call('confirmWaiting', $waiting->id, '900,0000');

    expect($waiting->refresh()->confirmed_at)->toBeNull();
});
