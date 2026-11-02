<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\User;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Mis pagos" in the app — the business's own payments, to look at
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'America/Caracas'));
});

function asPaymentsApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function paymentOf(User $owner, array $attributes = []): Payment
{
    return app(Tenant::class)->for($owner->business_id, fn (): Payment => Payment::factory()->create([
        'business_id' => $owner->business_id,
        'plan' => 'negocio',
        'amount' => 79,
        'currency' => 'USD',
        ...$attributes,
    ]));
}

function bolivarOwner(): User
{
    $ves = Currency::factory()->create(['code' => 'VES', 'symbol' => 'Bs.']);

    return payingUser(business: ['country_id' => Country::factory()->create(['currency_id' => $ves->id])->id])->refresh();
}

it('shows a business only its own payments, never another one\'s', function (): void {
    $owner = payingUser()->refresh();
    $other = payingUser()->refresh();
    $mine = paymentOf($owner, ['reference' => '111']);
    paymentOf($other, ['reference' => '222']);

    $rows = asPaymentsApp($owner)->getJson('/api/v1/payments')->assertOk()->json('data.history.rows');

    expect(collect($rows)->pluck('id')->all())->toBe([$mine->id]);
});

it('is the owner\'s, like the web screen: an agent of the business is refused', function (): void {
    $owner = payingUser();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles(['agent']);

    asPaymentsApp($agent->refresh())->getJson('/api/v1/payments')->assertForbidden();
});

it('offers bolivars to a business that lives in them, at the rate each payment kept', function (): void {
    ExchangeRate::factory()->create(['official' => 889.5754]);
    $owner = bolivarOwner();
    paymentOf($owner, ['exchange_rate' => 885.12, 'exchange_rate_on' => '2026-11-02']);
    paymentOf($owner);

    $data = asPaymentsApp($owner)->getJson('/api/v1/payments')->assertOk()->json('data');

    expect($data['money'])->toBe(['label' => __('exchange_rate.toggle.label'), 'usd' => 'USD', 'ves' => 'Bs'])
        ->and($data['next']['amount_ves'])->not->toBeNull()
        ->and($data['history']['rows'][1])->toMatchArray([
            'amount' => 'USD 79,00',
            'amount_ves' => 'Bs 69.924,48',
            'rate_note' => __('exchange_rate.toggle.paid_at', ['date' => '02/11/2026']),
        ])
        ->and($data['history']['rows'][0])->toMatchArray([
            'amount_ves' => null,
            'rate_note' => __('exchange_rate.toggle.no_rate'),
        ]);
});

it('does not offer bolivars to a business that does not live in them', function (): void {
    ExchangeRate::factory()->create();
    $owner = payingUser(business: ['country_id' => Country::factory()->create()->id])->refresh();

    $data = asPaymentsApp($owner)->getJson('/api/v1/payments')->assertOk()->json('data');

    expect($data['money'])->toBeNull()
        ->and($data['next']['rate_note'])->toBeNull();
});

it('lists Mis pagos in the app menu, where the phone now has the screen', function (): void {
    $items = collect(asPaymentsApp(payingUser()->refresh())->getJson('/api/v1/menu')->json('data'))
        ->flatMap(fn (array $item): array => [$item, ...($item['children'] ?? [])]);

    expect($items->pluck('screen')->all())->toContain('my-payments');
});
