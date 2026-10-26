<?php

declare(strict_types=1);

use App\Actions\Admin\FindOrphanInstances;
use App\Classes\Main\BusinessLedger;
use App\Classes\Main\PlatformBoard;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformSensorStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\DeviceChallengeCode;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Admin in the app: Inicio, Avisos and the menu
|--------------------------------------------------------------------------
| What the app shows comes from the same pieces and the same words as the web
| (`PlatformBoard`, `AdminAttention`, the admin menu), so the two cannot
| contradict each other. Only what the person may open is served.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    config()->set('atendia.security.staff_idle_minutes', 20);
});

/** A person of the platform's team, signed in the way the app does: password, then the code. */
function screensToken(string $role = 'admin'): string
{
    Mail::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->syncRoles([$role]);

    $id = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertStatus(202)->json('challenge.id');

    $code = '';
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => $code])
        ->assertOk()->json('token');
}

function screensGet(string $token, string $path): TestResponse
{
    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/admin/'.$path);
}

function watchSensor(string $key, string $group, PlatformSensorStatus $status, ?string $checked = null): PlatformSensor
{
    return PlatformSensor::query()->create([
        'sensor_key' => $key,
        'group' => $group,
        'status' => $status,
        'value' => '81 %',
        'detail' => 'quedan 9 GB de 48 GB',
        'failures' => 0,
        'checked_at' => $checked ?? now(),
        'changed_at' => now()->subHour(),
    ]);
}

test('Avisos reads from the very piece the web board reads, so what the app says is what the web says', function (): void {
    $business = Business::factory()->create(['name' => 'Ferretería El Tornillo']);
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create(['about_business_id' => $business->id]);
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessJoined)->create(['about_business_id' => $business->id]);
    watchSensor('disk', 'system', PlatformSensorStatus::Warn);
    watchSensor('redis', 'system', PlatformSensorStatus::Ok);

    $board = new PlatformBoard;
    $response = screensGet(screensToken(), 'alerts')->assertOk();

    expect($response->json('data.verdict'))->toBe($board->verdict)
        ->and($response->json('data.cards.0.value'))->toBe($board->counts['critical'])
        ->and(array_column($response->json('data.events.rows'), 'id'))->toBe(array_column($board->rows, 'id'))
        ->and($response->json('data.events.rows.0.business'))->toBe('Ferretería El Tornillo')
        ->and($response->json('data.title'))->toBe(__('platform_events.screen.title'))
        ->and($response->json('data.sensors.groups.0.title'))->toBe(__('platform_events.groups.system'));
});

test('both screens say how often they refresh themselves, so the phone keeps the promise the web\'s sentence makes', function (): void {
    $token = screensToken();

    expect(screensGet($token, 'alerts')->json('data.poll_seconds'))->toBe(PlatformBoard::POLL_SECONDS)
        ->and(screensGet($token, 'home')->json('data.poll_seconds'))->toBe(PlatformBoard::POLL_SECONDS)
        ->and(__('platform_events.screen.read_at', ['time' => '00:00:00']))->toContain((string) PlatformBoard::POLL_SECONDS);
});

test('the verdict names the platform\'s problem in the web\'s words', function (): void {
    watchSensor('disk', 'system', PlatformSensorStatus::Warn);

    $verdict = screensGet(screensToken(), 'alerts')->assertOk()->json('data.verdict');

    expect($verdict['level'])->toBe('warning')
        ->and($verdict['title'])->toBe(trans_choice('platform_events.screen.verdict_bad', 1))
        ->and($verdict['body'])->toBe(__('platform_events.screen.verdict_bad_body', ['names' => __('platform_events.sensors.disk.name')]));
});

test('a sensor nobody refreshed is shown unknown, never green, with its detail ready for the phone', function (): void {
    watchSensor('redis', 'system', PlatformSensorStatus::Ok, now()->subHours(3)->toDateTimeString());
    watchSensor('disk', 'system', PlatformSensorStatus::Ok);

    $sensors = collect(screensGet(screensToken(), 'alerts')->assertOk()->json('data.sensors.groups.0.sensors'))->keyBy('key');

    expect($sensors['redis']['status'])->toBe('unknown')
        ->and($sensors['redis']['value'])->toBe('—')
        ->and($sensors['redis']['line'])->toBe(__('platform_events.screen.stale'))
        ->and($sensors['disk']['status'])->toBe('ok')
        ->and($sensors['disk']['history'])->toHaveCount(24)
        ->and($sensors['disk'])->toHaveKeys(['name', 'checked', 'since', 'failures', 'says', 'fix', 'diagnostic']);
});

test('the list pages in SQL like the web: a page of twenty-five, and the next one on ask', function (): void {
    PlatformEvent::factory()->count(30)->severity(PlatformEventSeverity::Info)->create();
    $token = screensToken();

    $first = screensGet($token, 'alerts?filter=info')->assertOk();
    expect($first->json('data.events.rows'))->toHaveCount(25)->and($first->json('data.events.has_more'))->toBeTrue();

    $second = screensGet($token, 'alerts?filter=info&page=2')->assertOk();
    expect($second->json('data.events.rows'))->toHaveCount(30)->and($second->json('data.events.has_more'))->toBeFalse();
});

test('a filter the board does not offer reads as all of them', function (): void {
    PlatformEvent::factory()->count(2)->create();

    screensGet(screensToken(), 'alerts?filter=nonsense')->assertOk()->assertJsonPath('data.filter', 'all');
});

test('with an empty database Avisos still answers and says so, no 500 and no blank', function (): void {
    $data = screensGet(screensToken(), 'alerts')->assertOk()->json('data');

    expect($data['events']['rows'])->toBe([])
        ->and($data['events']['empty']['title'])->toBe(__('platform_events.screen.empty_title'))
        ->and($data['sensors']['groups'])->toBe([])
        ->and($data['quiet'])->not->toBeNull();
});

test('an alarm leads somewhere only when the app has that screen, and never to a button that goes nowhere', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::ReceiptReceived)->create(['about_business_id' => $business->id]);
    $token = screensToken();

    // A screen the app lacks leaves the row as information, with no destination; once it has it, the row leads there.
    config()->set('atendia.app.admin_screens', ['admin.dashboard', 'admin.alerts']);

    expect(screensGet($token, 'alerts')->json('data.events.rows.0.target'))->toBeNull();

    config()->set('atendia.app.admin_screens', ['admin.dashboard', 'admin.alerts', 'admin.payments']);

    expect(screensGet($token, 'alerts')->json('data.events.rows.0.target'))->toBe(['screen' => 'admin.payments', 'query' => []]);
});

test('a person without the key of Avisos is refused there, but not at the door of the app', function (): void {
    $token = screensToken('support');

    screensGet($token, 'alerts')->assertForbidden();
    screensGet($token, 'me')->assertOk();
    screensGet($token, 'home')->assertOk();
});

test('the menu is the web\'s admin menu, in its order, cut to the screens the app has and to what the person may open', function (): void {
    $admin = screensGet(screensToken('admin'), 'menu')->assertOk()->json('data');

    // "Negocios" and "Cobros" are groups in the web's menu: they keep their place and hold only the screens the app has.
    expect(array_column($admin, 'screen'))->toBe(['admin.dashboard', 'admin.alerts', null, null])
        ->and(array_column($admin, 'label'))->toBe([__('menu.admin_home'), __('menu.admin_alerts'), __('menu.admin_businesses'), __('menu.admin_billing')])
        ->and(array_column($admin[2]['children'], 'screen'))->toBe(['admin.businesses'])
        ->and(array_column($admin[3]['children'], 'screen'))->toBe(['admin.payments']);
});

test('support holds no key for the board, so its menu has the home and the businesses it may look up', function (): void {
    $support = screensGet(screensToken('support'), 'menu')->assertOk()->json('data');

    expect(array_column($support, 'screen'))->toBe(['admin.dashboard', null])
        ->and(array_column($support[1]['children'], 'screen'))->toBe(['admin.businesses']);
});

test('an alarm about a business leads to its ficha in the app, with the word of the web\'s button', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::WhatsAppDisconnected)->create(['about_business_id' => $business->id]);

    $data = screensGet(screensToken(), 'alerts')->assertOk()->json('data');

    expect($data['events']['rows'][0]['target'])->toBe(['screen' => 'admin.businesses', 'query' => ['negocio' => $business->id]])
        ->and($data['events']['go'])->toBe(__('platform_events.screen.go'))
        ->and($data['sensors']['labels']['open'])->toBe(__('platform_events.screen.detail_open'));
});

/** A business with the plan row that decides its situation. */
function ledgerBusiness(string $name, SubscriptionStatus $status = SubscriptionStatus::Active, array $plan = []): Business
{
    $business = Business::factory()->create(['name' => $name]);

    Subscription::factory()->for($business)->create([
        'plan' => 'negocio',
        'status' => $status,
        'billing_cycle' => 'monthly',
        'current_period_ends_at' => now()->addDays(10),
        ...$plan,
    ]);

    return $business->refresh();
}

test('Negocios reads from the very piece the web list reads: the rows, the count and the words', function (): void {
    ledgerBusiness('Odontología Norte');
    ledgerBusiness('Panadería La Esquina', SubscriptionStatus::PastDue, ['current_period_ends_at' => now()->subDays(2)]);

    $ledger = new BusinessLedger;
    $data = screensGet(screensToken(), 'businesses')->assertOk()->json('data');

    expect($data['rows'])->toBe($ledger->rows)
        ->and($data['count_label'])->toBe($ledger->countLabel)
        ->and($data['title'])->toBe(__('admin.businesses.title'))
        ->and($data['labels'])->toBe($ledger->labels)
        ->and($data['states']['options'])->toBe($ledger->stateOptions)
        ->and(array_column($data['rows'], 'name'))->toBe(['Odontología Norte', 'Panadería La Esquina']);

    $late = collect($data['rows'])->firstWhere('name', 'Panadería La Esquina');

    expect($late['state'])->toBe('past_due')
        ->and($late['state_label'])->toBe(__('admin.businesses.states.past_due'))
        ->and($late['owes'])->toStartWith('USD ')
        ->and(collect($data['rows'])->firstWhere('name', 'Odontología Norte')['owes'])->toBe('—');
});

test('odd input to the list is read as nothing, never a 500, and the last page promises no more', function (): void {
    ledgerBusiness('Odontología Norte');
    $token = screensToken();

    foreach (['search[]=a', 'state[]=paused', 'page=0', 'page=-3', 'page[]=2', 'page=99999999999'] as $odd) {
        screensGet($token, 'businesses?'.$odd)->assertOk()->assertJsonCount(1, 'data.rows');
    }

    expect(screensGet($token, 'businesses?page=99999999999')->json('data.has_more'))->toBeFalse();
});

test('the search finds without accents and the state cuts the list, as on the web', function (): void {
    ledgerBusiness('Odontología Norte');
    ledgerBusiness('Panadería La Esquina', SubscriptionStatus::Paused);
    $token = screensToken();

    expect(array_column(screensGet($token, 'businesses?search=odontologia')->json('data.rows'), 'name'))->toBe(['Odontología Norte'])
        ->and(array_column(screensGet($token, 'businesses?state=paused')->json('data.rows'), 'name'))->toBe(['Panadería La Esquina'])
        ->and(screensGet($token, 'businesses?search=zzzz')->json('data.empty'))->toBe(__('admin.businesses.no_match'));

    // A state the list does not offer reads as all of them, never as an error.
    expect(screensGet($token, 'businesses?state=nonsense')->assertOk()->json('data.rows'))->toHaveCount(2);
});

test('the list pages in SQL in tranches of twenty-five and says how many are held back', function (): void {
    Business::factory()->count(30)->create();
    $token = screensToken();

    $first = screensGet($token, 'businesses')->json('data');
    $second = screensGet($token, 'businesses?page=2')->json('data');

    expect($first['rows'])->toHaveCount(25)->and($first['has_more'])->toBeTrue()
        ->and($first['count_label'])->toBe(trans_choice('admin.businesses.count', 30, ['count' => 30]))
        ->and($second['rows'])->toHaveCount(30)->and($second['has_more'])->toBeFalse();
});

test('the ficha brings the three cards, what the plan row says about leaving and the last payments', function (): void {
    $business = ledgerBusiness('Estudio Contable Ríos', SubscriptionStatus::Active, ['canceled_at' => now()->subDay()]);
    Payment::factory()->for($business)->create(['plan' => 'negocio', 'status' => 'pending']);

    $ficha = screensGet(screensToken(), 'businesses/'.$business->id)->assertOk()->json('data');

    expect($ficha['name'])->toBe('Estudio Contable Ríos')
        ->and($ficha['removed'])->toBeFalse()
        ->and(array_column($ficha['cards'], 'key'))->toBe(['plan', 'next', 'owes'])
        ->and($ficha['cards'][0]['label'])->toBe(__('admin.businesses.card.plan'))
        ->and($ficha['cards'][0]['value'])->toBe(__('plan.names.negocio'))
        ->and($ficha['payments']['rows'])->toHaveCount(1)
        ->and($ficha['payments']['rows'][0]['status'])->toBe(__('billing.history.statuses.pending'));
});

test('a removed business opens too, marked as removed, and one that does not exist is a 404', function (): void {
    $business = ledgerBusiness('Kiosco El Faro');
    $business->delete();
    $token = screensToken();

    screensGet($token, 'businesses/'.$business->id)->assertOk()->assertJsonPath('data.removed', true);
    screensGet($token, 'businesses/99999999')->assertNotFound();
});

test('the bridge\'s orphan instances show only to whoever may manage businesses, seen and never freed from here', function (): void {
    Cache::put(FindOrphanInstances::CACHE_KEY, ['names' => ['business-77'], 'at' => now()->toIso8601String()], now()->addDay());

    $orphans = screensGet(screensToken('admin'), 'businesses')->assertOk()->json('data.orphans');

    expect($orphans['names'])->toBe(['business-77'])->and($orphans['title'])->toBe(__('admin.businesses.orphans.title'));
});

test('support may look businesses up but is not shown the orphan instances', function (): void {
    Cache::put(FindOrphanInstances::CACHE_KEY, ['names' => ['business-77'], 'at' => now()->toIso8601String()], now()->addDay());

    screensGet(screensToken('support'), 'businesses')->assertOk()->assertJsonPath('data.orphans', null);
});

test('without the key of Negocios the list and the ficha are closed, and a customer reads neither', function (): void {
    $business = ledgerBusiness('Taller Luz');
    $nobody = User::factory()->create(['email_verified_at' => now()]);
    $nobody->givePermissionTo('access-admin-panel');

    Mail::fake();
    $id = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', ['email' => $nobody->email, 'password' => 'password', 'device_name' => 'Pixel 8'])->assertStatus(202)->json('challenge.id');
    $code = '';
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });
    $token = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => $code])->json('token');

    screensGet($token, 'businesses')->assertForbidden();
    screensGet($token, 'businesses/'.$business->id)->assertForbidden();
});

test('a customer\'s token reads no business of the platform', function (): void {
    $token = appInstallToken(payingUser());

    screensGet($token, 'businesses')->assertForbidden()->assertJsonPath('code', 'staff_only');
});

test('Inicio serves the web\'s "needs you today" card with its words, and keeps every line even when the app lacks its screen', function (): void {
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->count(2)->create();
    $token = screensToken();

    $data = screensGet($token, 'home')->assertOk()->json('data');
    $alerts = collect($data['attention']['items'])->firstWhere('key', 'alerts');

    expect($data['title'])->toBe(__('admin.home.title'))
        ->and($data['attention']['title'])->toBe(__('ask_admin.today.title'))
        ->and($alerts['count'])->toBe(2)
        ->and($alerts['label'])->toBe(trans_choice('ask_admin.today.alerts', 2, ['count' => 2]))
        ->and($alerts['screen'])->toBe('admin.alerts');

    // Dropping the lines whose screen the app lacks would say "all clear" over what the web lists.
    config()->set('atendia.app.admin_screens', ['admin.dashboard']);
    $kept = collect(screensGet($token, 'home')->json('data.attention.items'))->firstWhere('key', 'alerts');

    expect($kept['count'])->toBe(2)->and($kept['screen'])->toBeNull();
});

test('Pagos reads from the very piece the web desk reads: the queue, the reviewed and the words', function (): void {
    $waiting = Payment::factory()->create(['reference' => '55512345', 'receipt_path' => 'receipts/secret.png', 'status' => 'pending']);
    $rejected = Payment::factory()->create(['status' => 'rejected', 'rejection_reason' => 'El comprobante no se lee']);
    $data = screensGet(screensToken(), 'payments')->assertOk()->json('data');

    expect($data['title'])->toBe(__('billing.admin.title'))
        ->and($data['poll_seconds'])->toBe(PlatformBoard::POLL_SECONDS)
        ->and($data['pending']['total'])->toBe(1)
        ->and($data['pending']['rows'])->toBe([[
            'id' => $waiting->id,
            'date' => $waiting->created_at->format('d/m/Y H:i'),
            'business' => $waiting->business->name,
            'concept' => __('billing.history.concept_line', ['plan' => __('plan.names.negocio')]),
            'amount' => 'USD 79,00',
            'reference' => '55512345',
        ]])
        ->and($data['recent']['rows'][0])->toMatchArray([
            'id' => $rejected->id,
            'status' => __('billing.history.statuses.rejected'),
            'tone' => 'danger',
            'reason' => 'El comprobante no se lee',
        ]);
});

test('a receipt with no reference reads as a dash and another currency keeps its own', function (): void {
    Payment::factory()->create(['status' => 'pending', 'reference' => null, 'currency' => 'VES', 'amount' => 1234.5]);
    $row = screensGet(screensToken(), 'payments')->json('data.pending.rows.0');

    expect($row['reference'])->toBe('—')->and($row['amount'])->toBe('VES 1.234,50');
});

test('Pagos never hands out the receipt file, and says so with an empty desk in the web\'s words', function (): void {
    Payment::factory()->create(['status' => 'pending', 'receipt_path' => 'receipts/secret.png']);
    $token = screensToken();

    expect(json_encode(screensGet($token, 'payments')->json()))->not->toContain('secret.png')->not->toContain('receipt');

    Payment::query()->delete();
    $empty = screensGet($token, 'payments')->assertOk()->json('data');

    expect($empty['pending']['rows'])->toBe([])
        ->and($empty['pending']['empty'])->toBe(__('billing.admin.empty'))
        ->and($empty['recent']['empty'])->toBe(__('billing.admin.recent_empty'));
});

test('the queue pages in SQL and the last page promises no more', function (): void {
    // Equal dates: only the id keeps the slices consistent from one call to the next.
    Payment::factory()->count(27)->create(['status' => 'pending', 'created_at' => now()->subDay()]);
    $token = screensToken();

    $first = screensGet($token, 'payments')->json('data.pending');
    $second = screensGet($token, 'payments?page=2')->json('data.pending');

    expect($first['rows'])->toHaveCount(25)->and($first['has_more'])->toBeTrue()->and($first['total'])->toBe(27)
        ->and($second['rows'])->toHaveCount(27)->and($second['has_more'])->toBeFalse()
        ->and(array_slice(array_column($second['rows'], 'id'), 0, 25))->toBe(array_column($first['rows'], 'id'));

    foreach (['page[]=2', 'page=0', 'page=-3', 'page=99999999999'] as $odd) {
        screensGet($token, 'payments?'.$odd)->assertOk();
    }

    expect(screensGet($token, 'payments?page=99999999999')->json('data.pending.has_more'))->toBeFalse();
});

test('without the key of Pagos the desk is closed', function (): void {
    screensGet(screensToken('support'), 'payments')->assertForbidden();
});

test('a customer\'s token reads nothing of Pagos', function (): void {
    screensGet(appInstallToken(payingUser()), 'payments')->assertForbidden()->assertJsonPath('code', 'staff_only');
});

test('Inicio with nothing pending offers the clear sentence of the web', function (): void {
    $data = screensGet(screensToken(), 'home')->assertOk()->json('data.attention');

    expect($data['items'])->toBe([])->and($data['clear'])->toBe(__('ask_admin.today.clear'));
});

test('a customer\'s token reads none of the three', function (): void {
    $token = appInstallToken(payingUser());

    foreach (['home', 'alerts', 'menu'] as $path) {
        screensGet($token, $path)->assertForbidden()->assertJsonPath('code', 'staff_only');
    }
});
