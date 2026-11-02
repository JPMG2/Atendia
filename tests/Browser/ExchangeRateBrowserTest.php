<?php

declare(strict_types=1);

use App\Actions\Billing\FetchExchangeRate;
use App\Models\Business;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Tasa de cambio and the USD/Bs toggle — the shots a human looks at
|--------------------------------------------------------------------------
| To check by EYE: the rate that charges reads first, the parallel says it
| never charges, and the toggle swaps every amount without moving the table.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('app.timezone', 'America/Caracas');
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'America/Caracas'));
    $this->seed(PlanSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    ExchangeRate::factory()->on('2026-11-02')->create(['official' => 885.1200, 'parallel' => 990.1000]);
    ExchangeRate::factory()->on('2026-11-03')->manual()->create(['official' => 887.0000]);
    ExchangeRate::factory()->on('2026-11-04')->create(['official' => 889.5754, 'parallel' => 993.8328]);
});

function rateShotAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    return $admin->refresh();
}

test('the exchange rate screen holds in its three states, at every width and theme', function (string $state, int $width, bool $dark): void {
    // Old: a Tuesday with nothing newer than last Wednesday.
    if ($state === 'stale') {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'America/Caracas'));
    }

    Cache::forever(FetchExchangeRate::LAST_CHECK, [
        'at' => now()->toIso8601String(),
        'bcv' => $state === 'ok' ? null : 'BCV page answered HTTP 503',
        'dolarapi' => in_array($state, ['sources_down', 'stale'], true) ? 'dolarapi answered HTTP 500' : null,
        'stored' => null,
    ]);

    $this->actingAs(rateShotAdmin());
    $page = visit(route('admin.exchange-rate'))->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Tasa de cambio')
        ->assertSee('889,5754')
        ->assertSee('993,8328')
        ->screenshot(filename: 'exchange-rate-'.$state.'-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([
    ['ok', 1280, false],
    ['ok', 390, true],
    ['fallback', 1280, true],
    ['sources_down', 390, false],
    ['stale', 390, false],
]);

test('mis pagos swaps dollars for bolivars with one tap', function (int $width, bool $dark): void {
    $ves = Currency::factory()->create(['code' => 'VES', 'symbol' => 'Bs.']);
    $business = Business::factory()->create([
        'name' => 'Panadería La Espiga',
        'country_id' => Country::factory()->create(['currency_id' => $ves->id])->id,
        'timezone' => 'America/Caracas',
    ]);
    Subscription::query()->where('business_id', $business->id)->delete();
    Subscription::factory()->create(['business_id' => $business->id, 'plan' => 'negocio', 'billing_cycle' => 'monthly', 'current_period_ends_at' => now()->addDays(12)]);
    Payment::factory()->create(['business_id' => $business->id, 'plan' => 'negocio', 'amount' => 79, 'currency' => 'USD', 'exchange_rate' => 885.12, 'exchange_rate_on' => '2026-11-02']);
    Payment::factory()->create(['business_id' => $business->id, 'plan' => 'negocio', 'amount' => 79, 'currency' => 'USD']);
    $owner = User::factory()->create(['business_id' => $business->id]);
    $owner->assignRole('client');

    $this->actingAs($owner->refresh());
    $page = visit(route('my-payments'))->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('USD 79,00')
        ->screenshot(filename: 'my-payments-usd-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click('Bs')
        ->assertSee('Bs 69.924,48')
        ->assertSee('Sin tasa guardada para este pago')
        ->screenshot(filename: 'my-payments-ves-'.$width.'-'.($dark ? 'dark' : 'light'))
        // The choice is remembered: back on the screen, it opens in bolivars.
        ->refresh()
        ->assertSee('Bs 69.924,48')
        ->click('Subir comprobante')
        ->assertSee('Cuánto transferir en bolívares')
        ->assertSee('Bs 70.276,46')
        ->screenshot(filename: 'my-payments-receipt-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([
    [1280, false],
    [390, true],
]);

test('the admin payments desk swaps every amount with the same toggle', function (): void {
    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    Payment::factory()->create(['business_id' => $business->id, 'plan' => 'negocio', 'amount' => 79, 'currency' => 'USD', 'exchange_rate' => 889.5754, 'exchange_rate_on' => '2026-11-04', 'reference' => '55512345']);

    $this->actingAs(rateShotAdmin());
    visit(route('admin.payments'))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('USD 79,00')
        ->click('Bs')
        ->assertSee('Bs 70.276,46')
        ->screenshot(filename: 'admin-payments-ves-1280-light');
});
