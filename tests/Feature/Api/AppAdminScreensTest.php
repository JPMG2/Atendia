<?php

declare(strict_types=1);

use App\Actions\Admin\FindOrphanInstances;
use App\Classes\Main\BusinessLedger;
use App\Classes\Main\CollectionsDesk;
use App\Classes\Main\OpeningHours;
use App\Classes\Main\PlatformBoard;
use App\Classes\Main\SupportQueue;
use App\Dto\CatalogColumnDto;
use App\Enums\AdoptionStep;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformSensorStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Mail\DeviceChallengeCode;
use App\Models\AdoptionNudge;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\CatalogForm;
use App\Models\Company;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Models\Currency;
use App\Models\CurrentStatus;
use App\Models\DemoTag;
use App\Models\HelpArticle;
use App\Models\MentionGoal;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\Province;
use App\Models\Region;
use App\Models\SeasonalWindow;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\SupportTicketMessage;
use App\Models\TaxCondition;
use App\Models\User;
use App\Models\WhatsappVariant;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

test('the last page a call may ask for says there is no more, so the button goes away', function (): void {
    PlatformEvent::factory()->count(210)->severity(PlatformEventSeverity::Info)->create();

    $last = screensGet(screensToken(), 'alerts?filter=info&page=8')->assertOk();

    expect($last->json('data.events.rows'))->toHaveCount(200)->and($last->json('data.events.has_more'))->toBeFalse();
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
    expect(array_column($admin, 'screen'))->toBe(['admin.dashboard', 'admin.alerts', null, null, 'admin.support', null])
        ->and(array_column($admin, 'label'))->toBe([__('menu.admin_home'), __('menu.admin_alerts'), __('menu.admin_businesses'), __('menu.admin_billing'), __('menu.admin_support'), __('menu.admin_platform')])
        ->and(array_column($admin[2]['children'], 'screen'))->toBe(['admin.businesses', 'admin.adoption', 'admin.incidents', 'admin.contacts', 'admin.compliance'])
        ->and(array_column($admin[3]['children'], 'screen'))->toBe(['admin.payments', 'admin.collections'])
        ->and(array_column($admin[5]['children'], 'screen'))->toBe(['admin.company', 'admin.catalogs']);
});

test('support holds no key for the board, so its menu has the home, the businesses it may look up and its own desk', function (): void {
    $support = screensGet(screensToken('support'), 'menu')->assertOk()->json('data');

    expect(array_column($support, 'screen'))->toBe(['admin.dashboard', null, 'admin.support'])
        ->and(array_column($support[1]['children'], 'screen'))->toBe(['admin.businesses']);
});

test('Soporte carries the count of reports waiting on a reply, the same number on the web\'s sidebar and in the app\'s menu', function (): void {
    SupportTicket::factory()->count(2)->create();
    SupportTicket::factory()->resolved()->create();
    $token = screensToken();

    $support = collect(screensGet($token, 'menu')->json('data'))->firstWhere('screen', 'admin.support');
    $this->actingAs(User::role('admin')->latest('id')->firstOrFail(), 'web');
    $web = Menu::withAdminBadges(Menu::tree('admin'))->firstWhere('route_name', 'admin.support');

    // The menu's cheap count and the queue's own tab are the same number.
    expect(SupportTicket::queueCounts()['answer'])->toBe(2)
        ->and(SupportTicket::awaitingReplyCount())->toBe(2)
        ->and($support['badge'])->toBe('2')
        ->and($web->badge)->toBe('2')
        ->and(collect(screensGet($token, 'menu')->json('data'))->firstWhere('screen', 'admin.alerts')['badge'])->toBeNull();

    SupportTicket::query()->update(['status' => SupportTicketStatus::Resolved]);

    expect(collect(screensGet($token, 'menu')->json('data'))->firstWhere('screen', 'admin.support')['badge'])->toBeNull();
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

    // The same greeting the web's Inicio and the customer's home open with.
    expect($data['title'])->toBe(__('admin.home.greeting', ['name' => User::role('admin')->latest('id')->value('name')]))
        ->and($data['attention']['title'])->toBe(__('ask_admin.today.title'))
        ->and($alerts['count'])->toBe(2)
        ->and($alerts['label'])->toBe(trans_choice('ask_admin.today.alerts', 2, ['count' => 2]))
        ->and($alerts['screen'])->toBe('admin.alerts')
        ->and($data['mood'])->toBe('alert');

    // Dropping the lines whose screen the app lacks would say "all clear" over what the web lists.
    config()->set('atendia.app.admin_screens', ['admin.dashboard']);
    $kept = collect(screensGet($token, 'home')->json('data.attention.items'))->firstWhere('key', 'alerts');

    expect($kept['count'])->toBe(2)->and($kept['screen'])->toBeNull();

    // With no critical alert open the mascot is calm; the card's line decides, not a second count.
    PlatformEvent::query()->delete();
    expect(screensGet($token, 'home')->json('data.mood'))->toBe('calm');
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

test('Cobranza reads from the very piece the web screen reads: the figures, the order of the queue and the words', function (): void {
    $this->seed(PlanSeeder::class);
    ledgerBusiness('Kiosco La Esquina', SubscriptionStatus::PastDue, ['plan' => 'emprende', 'current_period_ends_at' => now()->subDays(60)]);
    $big = ledgerBusiness('Centro Odontológico', SubscriptionStatus::Paused, ['plan' => 'premium', 'current_period_ends_at' => now()->subDays(3)]);
    $data = screensGet(screensToken(), 'collections')->assertOk()->json('data');

    expect($data['title'])->toBe(__('collections.title'))
        // The biggest account first, not the one that owed longest.
        ->and(array_column($data['rows'], 'business'))->toBe(['Centro Odontológico', 'Kiosco La Esquina'])
        ->and($data['rows'][0])->toMatchArray([
            'ficha' => $big->id,
            'plan' => __('plan.names.premium'),
            'state' => 'paused',
            'state_label' => __('collections.states.paused'),
            'tone' => 'danger',
            'since' => 'hace 3 días',
        ])
        ->and($data['rows'][1]['tone'])->toBe('warning')
        ->and($data['cards'][0]['value'])->toBe('USD '.CollectionsDesk::formatPrice(149 + 29))
        ->and($data['cards'][1]['value'])->toBe('2')
        ->and($data['cards'][1]['hint'])->toBe(__('collections.risk.owing_split', ['grace' => 1, 'paused' => 1]))
        ->and($data['cards'][1]['target'])->toBe(['screen' => 'admin.businesses', 'query' => ['estado' => 'problem']])
        // The first month measured says it has nothing to compare with: no delta, no arrow.
        ->and($data['cards'][2]['delta'])->toBeNull()->and($data['cards'][2]['hint'])->toBe(__('collections.risk.no_history'));
});

test('Cobranza with nobody to ring draws the empty state and no figures, as the web does', function (): void {
    $data = screensGet(screensToken(), 'collections')->assertOk()->json('data');

    expect($data['rows'])->toBe([])
        ->and($data['cards'])->toBe([])
        ->and($data['empty'])->toBe(['title' => __('collections.empty_title'), 'body' => __('collections.empty_body')]);
});

test('Cobranza hands over the queue in slices without changing the figures above it', function (): void {
    $this->seed(PlanSeeder::class);

    foreach (range(1, 27) as $n) {
        ledgerBusiness('Negocio '.$n, SubscriptionStatus::PastDue, ['current_period_ends_at' => now()->subDays(2)]);
    }

    $token = screensToken();
    $first = screensGet($token, 'collections')->json('data');
    $second = screensGet($token, 'collections?page=2')->json('data');

    expect($first['rows'])->toHaveCount(25)->and($first['has_more'])->toBeTrue()
        ->and($first['cards'][1]['value'])->toBe('27')
        ->and($second['rows'])->toHaveCount(27)->and($second['has_more'])->toBeFalse()
        ->and(array_slice(array_column($second['rows'], 'id'), 0, 25))->toBe(array_column($first['rows'], 'id'));

    foreach (['page[]=2', 'page=0', 'page=-3', 'page=99999999999'] as $odd) {
        screensGet($token, 'collections?'.$odd)->assertOk();
    }
});

test('without the key of the payments Cobranza is closed', function (): void {
    screensGet(screensToken('support'), 'collections')->assertForbidden();
});

test('a customer\'s token reads nothing of Cobranza', function (): void {
    screensGet(appInstallToken(payingUser()), 'collections')->assertForbidden()->assertJsonPath('code', 'staff_only');
});

test('Compañía hands over the company\'s data in the words of the web form, with a dash where nothing was filled in', function (): void {
    $region = Region::factory()->create(['name' => 'Zona Norte']);
    $company = Company::factory()->create([
        'legal_name' => 'Aliadito Servicios S.A.',
        'brand_name' => 'Aliadito',
        'tagline' => null,
        'region_id' => $region->id,
        'tax_id' => '30123456789',
        'opening_schedule' => ['1' => ['09:00', '18:00'], '2' => ['09:00', '18:00']],
        'payment_instructions' => null,
    ]);
    SocialLink::factory()->for($company, 'linkable')->create(['sort_order' => 2, 'url' => 'https://example.com/second']);
    SocialLink::factory()->for($company, 'linkable')->create(['sort_order' => 1, 'url' => 'https://example.com/first']);

    $data = screensGet(screensToken(), 'company')->assertOk()->json('data');
    $fields = collect($data['sections'])->flatMap(fn (array $section): array => $section['fields'])->keyBy('key');

    expect($data['title'])->toBe(__('company.title'))
        ->and(array_column($data['sections'], 'key'))->toBe(['identity', 'address', 'tax', 'footer', 'contact'])
        ->and($data['sections'][0]['title'])->toBe(__('company.identity.title'))
        ->and($fields['legal_name'])->toBe(['key' => 'legal_name', 'label' => __('company.fields.legal_name'), 'value' => 'Aliadito Servicios S.A.', 'mono' => false])
        ->and($fields['tax_id']['mono'])->toBeTrue()
        // A field nobody filled in reads as a dash, never as an empty string.
        ->and($fields['tagline']['value'])->toBe('—')->and($fields['payment_instructions']['value'])->toBe('—')
        ->and($fields['region']['value'])->toBe('Zona Norte')
        ->and($fields['province']['value'])->toBe($region->province->name)
        ->and($fields['country']['value'])->toBe($region->province->country->name)
        // The week in words, as the web's own helper says it.
        ->and($fields['opening_schedule']['value'])->toBe((new OpeningHours(OpeningHours::normalize($company->opening_schedule), 'UTC'))->summary)
        ->and(array_column($data['social']['rows'], 'url'))->toBe(['https://example.com/first', 'https://example.com/second'])
        ->and($data['logos']['light']['url'])->toBeNull();
});

test('Compañía with no company ever loaded is the web\'s blank form: every field a dash, no error', function (): void {
    $data = screensGet(screensToken(), 'company')->assertOk()->json('data');
    $values = collect($data['sections'])->flatMap(fn (array $section): array => array_column($section['fields'], 'value'))->unique()->all();

    expect($values)->toBe(['—'])->and($data['social']['rows'])->toBe([]);
});

/** The opening-hours line of the company sheet, as the app reads it. */
function companyHours(): string
{
    return collect(screensGet(screensToken(), 'company')->assertOk()->json('data.sections.4.fields'))->firstWhere('key', 'opening_schedule')['value'];
}

test('a company without a declared week reads as not set, never as closed', function (): void {
    Company::factory()->create(['opening_schedule' => null]);

    expect(companyHours())->toBe('—');
});

test('a company with a declared week and no shift at all reads as closed', function (): void {
    Company::factory()->create(['opening_schedule' => []]);

    expect(companyHours())->toBe(__('company.hours.closed'));
});

test('without the key of the company screen Compañía is closed', function (): void {
    screensGet(screensToken('support'), 'company')->assertForbidden();
});

test('a customer\'s token reads nothing of Compañía', function (): void {
    screensGet(appInstallToken(payingUser()), 'company')->assertForbidden()->assertJsonPath('code', 'staff_only');
});

/** A report of a business, with the fields the queue reads; the rest is the factory's. */
function supportReport(Business $business, array $fields = []): SupportTicket
{
    return SupportTicket::factory()->create(['business_id' => $business->id, 'code' => SupportTicket::freshCode(), ...$fields]);
}

test('the support queue reads from the very pieces the web desk reads: counts, order, rows and words', function (): void {
    config()->set('atendia.support.overdue_hours_high', 4);
    $late = ledgerBusiness('Panadería La Esquina', SubscriptionStatus::PastDue, ['current_period_ends_at' => now()->subDays(2)]);
    $fine = ledgerBusiness('Ferretería El Tornillo');
    $urgent = supportReport($late, ['priority' => 'high', 'created_at' => now()->subHours(30), 'body' => 'No me deja guardar los productos.']);
    $newer = supportReport($fine, ['status' => 'waiting', 'answered_at' => now()->subHours(2)]);
    $data = screensGet(screensToken(), 'support')->assertOk()->json('data');
    $row = $data['rows'][0];

    expect($data['title'])->toBe(__('support.admin.title'))
        ->and($data['queue'])->toBe('answer')
        ->and(array_column($data['tabs'], 'key'))->toBe(['answer', 'waiting', 'blocked', 'resolved', 'help'])
        ->and($data['tabs'][0])->toMatchArray(['label' => __('support.admin.tabs.answer'), 'count' => 1, 'late' => true])
        ->and($data['tabs'][1]['count'])->toBe(1)
        // The help tab carries no badge while nothing needs eyes.
        ->and($data['tabs'][4]['count'])->toBeNull()
        ->and($data['total'])->toBe(trans_choice('support.admin.count', 2, ['count' => 2]))
        ->and($data['oldest'])->toBe(__('support.admin.oldest', ['time' => $urgent->refresh()->waitLabel()]))
        ->and($row)->toMatchArray([
            'id' => $urgent->id,
            'code' => $urgent->code,
            'excerpt' => 'No me deja guardar los productos.',
            'late' => true,
            'assignee' => null,
            'priority' => ['label' => __('support.admin.priorities.high'), 'tone' => 'danger'],
            'blocked' => null,
        ])
        ->and($row['wait']['overdue'])->toBe(__('support.admin.overdue', ['hours' => 4]))
        ->and($row['business'])->toMatchArray(['id' => $late->id, 'name' => 'Panadería La Esquina'])
        ->and($row['business']['payment']['tone'])->toBe('warning')
        ->and($data['rows'])->toHaveCount(1)
        ->and($newer->id)->not->toBe($urgent->id);
});

test('the blocked queue says what is missing, who follows and when, and the waiting one says it waits for the business', function (): void {
    $business = ledgerBusiness('Kiosco La Esquina');
    supportReport($business, ['status' => 'blocked', 'blocked_tried' => 'Reiniciamos el puente.', 'blocked_missing' => 'Que Meta apruebe la plantilla.', 'blocked_owner' => 'Meta', 'blocked_due' => today()->subDay(), 'blocked_at' => now()->subDays(3)]);
    supportReport($business, ['status' => 'waiting', 'answered_at' => now()->subHour()]);
    $token = screensToken();

    $blocked = screensGet($token, 'support?queue=blocked')->assertOk()->json('data');

    expect($blocked['rows'][0]['blocked'])->toBe([
        'missing' => 'Que Meta apruebe la plantilla.',
        'owner' => 'Meta',
        'late' => true,
        'follows' => __('support.admin.block.late', ['date' => today()->subDay()->format('d/m')]),
    ])->and($blocked['tabs'][2]['late'])->toBeTrue();
});

test('the waiting queue reads as waiting for the business', function (): void {
    supportReport(ledgerBusiness('Kiosco La Esquina'), ['status' => 'waiting', 'answered_at' => now()->subHour()]);
    $waiting = screensGet(screensToken(), 'support?queue=waiting')->assertOk()->json('data');

    expect($waiting['rows'][0]['wait']['note'])->toBe(__('support.admin.waiting_business'))
        ->and($waiting['rows'][0]['wait']['overdue'])->toBeNull();
});

test('the queue is cut by search, kind and owner, and says which truth an empty list is', function (): void {
    $business = ledgerBusiness('Panadería La Esquina');
    $mine = supportReport($business, ['body' => 'Falla el calendario de citas', 'kind' => 'problem']);
    supportReport($business, ['body' => 'Una idea para el informe', 'kind' => 'idea']);
    $token = screensToken();

    $cut = screensGet($token, 'support?search=calendario')->assertOk()->json('data');
    $nobody = screensGet($token, 'support?who=none&kind=idea')->assertOk()->json('data');
    $nothing = screensGet($token, 'support?search=zzzz')->assertOk()->json('data');

    expect(array_column($cut['rows'], 'id'))->toBe([$mine->id])
        ->and($cut['is_filtered'])->toBeTrue()->and($cut['applied']['search'])->toBe('calendario')
        ->and($nobody['rows'])->toHaveCount(1)
        ->and($nothing['rows'])->toBe([])
        ->and($nothing['empty'])->toBe(['title' => __('support.admin.no_match_title'), 'body' => __('support.admin.no_match_body')]);
});

test('a queue that is simply clear and a desk nobody ever wrote to say different things', function (): void {
    $token = screensToken();

    expect(screensGet($token, 'support')->json('data.empty'))->toBe(['title' => __('support.admin.empty_title'), 'body' => __('support.admin.empty_body')]);
});

test('odd input to the support queue is read as nothing, never a 500, and the web\'s ceiling of 100 holds', function (): void {
    $business = ledgerBusiness('Panadería La Esquina');

    foreach (range(1, 105) as $n) {
        supportReport($business);
    }

    $token = screensToken();

    foreach (['search[]=a', 'kind[]=x', 'business[]=1', 'who[]=1', 'queue[]=answer', 'page=0', 'page=-3', 'page[]=2', 'business=abc', 'queue=nonsense', 'who=1%27'] as $odd) {
        screensGet($token, 'support?'.$odd)->assertOk();
    }

    $first = screensGet($token, 'support')->json('data');
    $last = screensGet($token, 'support?page=99999999999')->json('data');

    expect($first['rows'])->toHaveCount(25)->and($first['has_more'])->toBeTrue()
        ->and($last['rows'])->toHaveCount(100)->and($last['has_more'])->toBeFalse();
});

test('a report opens with its thread, its notes, what is blocked, what the widget captured and its customer', function (): void {
    config()->set('atendia.support.overdue_hours', 24);
    $business = ledgerBusiness('Panadería La Esquina', SubscriptionStatus::PastDue, ['current_period_ends_at' => now()->subDays(2)]);
    $ticket = supportReport($business, [
        'status' => 'blocked',
        'body' => 'No puedo conectar mi WhatsApp.',
        'blocked_tried' => 'Reiniciamos el puente.',
        'blocked_missing' => 'Que Meta apruebe la plantilla.',
        'blocked_owner' => 'Meta',
        'blocked_due' => today()->addDays(3),
        'blocked_at' => now()->subDay(),
        'attachment_path' => 'support/secret-shot.png',
        'context' => ['url' => 'https://example.com/whatsapp', 'viewport' => '390x800', 'errors' => 'TypeError: x is undefined'],
        'ai_suggestion' => ['summary' => 'No conecta el QR.', 'confidence' => 'high', 'kind' => 'problem', 'priority' => 'normal', 'draft' => 'Escanea el QR de nuevo desde Ajustes.', 'used' => [], 'reason' => 'Coincide con la guía.'],
    ]);
    $team = User::factory()->create(['name' => 'Ana Soporte']);
    SupportTicketMessage::query()->create(['support_ticket_id' => $ticket->id, 'business_id' => $business->id, 'user_id' => $team->id, 'body' => 'Estamos revisándolo.', 'is_internal' => false, 'delivery' => 'whatsapp']);
    SupportTicketMessage::query()->create(['support_ticket_id' => $ticket->id, 'business_id' => $business->id, 'user_id' => $team->id, 'body' => 'Nota: parece el token vencido.', 'is_internal' => true]);
    supportReport($business, ['status' => 'resolved', 'resolved_at' => now()]);

    $data = screensGet(screensToken(), 'support/'.$ticket->id)->assertOk()->json('data');

    expect($data['head'])->toBe(['title' => 'Panadería La Esquina', 'subtitle' => $ticket->code.' · '.$ticket->kind->name.' · '.SupportQueue::screenName('my-products')])
        ->and($data['state']['status']['label'])->toBe(__('support.admin.statuses.blocked'))
        ->and(array_column($data['panels'], 'key'))->toBe(['thread', 'notes', 'customer'])
        ->and($data['panels'][1]['badge'])->toBe(1)
        ->and($data['thread']['report']['body'])->toBe('No puedo conectar mi WhatsApp.')
        // The file is announced, never served: no path, no address.
        ->and($data['thread']['report']['attachment'])->toBe(__('support.admin.attachment'))
        ->and(json_encode($data))->not->toContain('secret-shot')
        ->and($data['thread']['answers'][0])->toMatchArray(['body' => 'Estamos revisándolo.', 'author' => 'Ana Soporte'])
        ->and($data['thread']['answers'][0]['delivery'])->toBe(['label' => __('support.admin.delivery.whatsapp'), 'tone' => 'brand'])
        ->and($data['notes']['rows'][0]['body'])->toBe('Nota: parece el token vencido.')
        ->and($data['handoff']['rows'][1])->toBe(['label' => __('support.admin.block.missing'), 'value' => 'Que Meta apruebe la plantilla.'])
        ->and($data['context']['errors']['lines'])->toBe(['TypeError: x is undefined'])
        ->and($data['ai']['draft'])->toBe('Escanea el QR de nuevo desde Ajustes.')
        ->and($data['ai']['confidence']['tone'])->toBe('success')
        ->and($data['customer']['rows'][2])->toMatchArray(['label' => __('support.admin.customer.payment_label'), 'tone' => 'warning'])
        ->and($data['customer']['others']['rows'])->toHaveCount(1)
        ->and($data['customer']['business'])->toBe($business->id)
        ->and($data['settled'])->toBeNull();
});

test('a settled report ends on how long it took, and the AI reading is gone', function (): void {
    $ticket = supportReport(ledgerBusiness('Kiosco La Esquina'), ['status' => 'resolved', 'resolved_at' => now(), 'ai_suggestion' => ['summary' => 'x', 'confidence' => 'low', 'draft' => 'y']]);
    $data = screensGet(screensToken(), 'support/'.$ticket->id)->assertOk()->json('data');

    expect($data['settled'])->toStartWith(substr(__('support.admin.resolved_in', ['time' => 'X']), 0, 12))->and($data['ai'])->toBeNull();
});

test('a report that does not exist is a plain 404, and ids out of range never reach the database', function (): void {
    $token = screensToken();

    screensGet($token, 'support/999999')->assertNotFound();
    screensGet($token, 'support/99999999999999999999')->assertNotFound();
    screensGet($token, 'support/abc')->assertNotFound();
});

test('a report of a business that closed its account still opens, with no customer beside it', function (): void {
    $business = ledgerBusiness('Kiosco La Esquina');
    $ticket = supportReport($business, ['body' => 'Quiero cerrar mi cuenta.']);
    $business->delete();

    $data = screensGet(screensToken(), 'support/'.$ticket->id)->assertOk()->json('data');

    expect($data['thread']['report']['body'])->toBe('Quiero cerrar mi cuenta.')->and($data['customer'])->toBeNull();
});

test('a reading or a capture written in an odd shape is read as text, never a crash', function (): void {
    $ticket = supportReport(ledgerBusiness('Kiosco La Esquina'), [
        'context' => ['url' => ['not', 'a', 'string'], 'errors' => ['x', 'y'], 'viewport' => '390x800'],
        'ai_suggestion' => ['summary' => ['x'], 'confidence' => ['high'], 'kind' => ['problem'], 'draft' => 'Borrador válido.', 'used' => [['a'], 'b'], 'looks_like' => ['ATN-1'], 'reason' => ['y']],
    ]);

    $data = screensGet(screensToken(), 'support/'.$ticket->id)->assertOk()->json('data');

    expect($data['ai']['draft'])->toBe('Borrador válido.')->and($data['ai']['summary'])->toBe('')->and($data['ai']['confidence']['tone'])->toBe('neutral')
        ->and($data['context']['rows'][0]['label'])->not->toBe('');
});

test('a kind the desk does not know is no filter, and says so', function (): void {
    supportReport(ledgerBusiness('Kiosco La Esquina'));
    $data = screensGet(screensToken(), 'support?kind=zzz')->assertOk()->json('data');

    expect($data['is_filtered'])->toBeFalse()->and($data['applied']['kind'])->toBe('')->and($data['rows'])->toHaveCount(1);
});

test('reports that arrived at the same moment keep their order as the app asks for more', function (): void {
    $business = ledgerBusiness('Panadería La Esquina');
    $moment = now()->subDay();

    foreach (range(1, 27) as $n) {
        supportReport($business, ['created_at' => $moment]);
    }

    $token = screensToken();
    $first = screensGet($token, 'support')->json('data.rows');
    $second = screensGet($token, 'support?page=2')->json('data.rows');

    expect($first)->toHaveCount(25)->and($second)->toHaveCount(27)
        ->and(array_slice(array_column($second, 'id'), 0, 25))->toBe(array_column($first, 'id'));
});

test('the help tab brings the answers that fail, the stale ones and the screens reported most', function (): void {
    HelpArticle::factory()->create(['title' => 'Cómo conectar WhatsApp', 'helpful_count' => 1, 'unhelpful_count' => 5]);
    HelpArticle::factory()->create(['title' => 'Cambiar el horario', 'updated_at' => now()->subMonths(8)]);
    supportReport(ledgerBusiness('Kiosco La Esquina'), ['screen' => 'my-products']);
    $data = screensGet(screensToken(), 'support/help')->assertOk()->json('data');

    expect($data['failing']['rows'][0])->toMatchArray(['title' => 'Cómo conectar WhatsApp', 'count' => 5])
        ->and($data['stale']['rows'][0]['title'])->toBe('Cambiar el horario')
        ->and($data['pain']['rows'][0])->toBe(['screen' => SupportQueue::screenName('my-products'), 'total' => 1])
        ->and($data['empty'])->toBeNull();
});

test('the help tab with nothing to look at says so, as the web does', function (): void {
    expect(screensGet(screensToken(), 'support/help')->json('data.empty'))->toBe(['title' => __('support.admin.help_empty_title'), 'body' => __('support.admin.help_empty_body')]);
});

test('the support role holds the key of Soporte', function (): void {
    supportReport(ledgerBusiness('Kiosco La Esquina'));

    screensGet(screensToken('support'), 'support')->assertOk();
});

test('another staff role without the key of Soporte is refused at its three doors', function (): void {
    $ticket = supportReport(ledgerBusiness('Kiosco La Esquina'));

    Role::findOrCreate('desk', 'web')->givePermissionTo(Permission::findByName('access-admin-panel', 'web'));
    $token = screensToken('desk');

    screensGet($token, 'support')->assertForbidden();
    screensGet($token, 'support/help')->assertForbidden();
    screensGet($token, 'support/'.$ticket->id)->assertForbidden();
});

test('a customer\'s token reads nothing of Soporte', function (): void {
    screensGet(appInstallToken(payingUser()), 'support')->assertForbidden()->assertJsonPath('code', 'staff_only');
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

/** The slugs of every master a hub answer lists, in the order it lists them. */
function catalogSlugs(array $groups): array
{
    return collect($groups)->flatMap(fn (array $group): array => array_column($group['masters'], 'slug'))->all();
}

test('Catálogos lists, grouped as the web\'s rail groups them, only the masters the app can already read', function (): void {
    $this->seed(CatalogFormSeeder::class);

    $data = screensGet(screensToken(), 'catalogs')->assertOk()->json('data');

    // Readable = its model says its own columns; the other 7 masters are not drawn until they do.
    // The order is the rail's: by the master's `order`, a group appearing where its first master does.
    expect(catalogSlugs($data['groups']))->toBe(['country', 'province', 'region', 'country-holiday', 'currency', 'tax-condition', 'plan', 'business-sector', 'business-activity', 'service-type', 'service-modality', 'service-attribute', 'status', 'social-network', 'mention-goal', 'seasonal-window', 'demo-tag', 'whatsapp-variant', 'support-reply', 'adoption-nudge', 'support-ticket-kind'])
        ->and(array_column($data['groups'], 'label'))->toBe(['Ubicaciones', 'Facturación', 'Negocio', 'Servicios', 'Sistema', 'Landing', 'Soporte'])
        ->and(array_map(fn (array $group): array => array_column($group['masters'], 'slug'), $data['groups']))->toBe([['country', 'province', 'region', 'country-holiday'], ['currency', 'tax-condition', 'plan'], ['business-sector', 'business-activity'], ['service-type', 'service-modality', 'service-attribute'], ['status', 'social-network', 'mention-goal'], ['seasonal-window', 'demo-tag', 'whatsapp-variant'], ['support-reply', 'adoption-nudge', 'support-ticket-kind']])
        // All 21 masters of the web are read now.
        ->and(count(catalogSlugs($data['groups'])))->toBe(CatalogForm::query()->count())
        ->and($data['groups'][0]['masters'][0])->toBe(['slug' => 'country', 'title' => 'Países', 'icon' => 'globe'])
        ->and($data['title'])->toBe(__('catalog.hub.rail_label'))
        ->and($data['search'])->toBe(['label' => __('catalog.hub.search_label'), 'placeholder' => __('catalog.hub.search_placeholder')])
        ->and($data['none'])->toBe(__('catalog.hub.none'))
        ->and($data['no_matches'])->toBe(__('catalog.hub.no_matches'));
});

test('Catálogos cuts the hub by the permission of each master, as the web\'s rail does', function (): void {
    $this->seed(CatalogFormSeeder::class);
    Role::findOrCreate('shelf', 'web')->givePermissionTo(['access-admin-panel', 'catalogs.manage', 'catalog.status']);
    $token = screensToken('shelf');

    expect(catalogSlugs(screensGet($token, 'catalogs')->assertOk()->json('data.groups')))->toBe(['status']);

    screensGet($token, 'catalogs/status')->assertOk();
    // A master of the hub this person may not open is not found, never "forbidden": the hub never named it.
    screensGet($token, 'catalogs/province')->assertNotFound();
});

test('Catálogos needs the web\'s key for the screen: support and a role without it are refused', function (): void {
    $this->seed(CatalogFormSeeder::class);
    Role::findOrCreate('desk', 'web')->givePermissionTo(Permission::findByName('access-admin-panel', 'web'));

    foreach (['support', 'desk'] as $role) {
        $token = screensToken($role);

        screensGet($token, 'catalogs')->assertForbidden();
        screensGet($token, 'catalogs/status')->assertForbidden();
    }
});

test('a customer\'s token reads nothing of Catálogos', function (): void {
    $token = appInstallToken(payingUser());

    screensGet($token, 'catalogs')->assertForbidden()->assertJsonPath('code', 'staff_only');
    screensGet($token, 'catalogs/status')->assertForbidden()->assertJsonPath('code', 'staff_only');
});

test('Estados serves the web\'s columns, its rows with only what a column draws, and its words', function (): void {
    $this->seed(CatalogFormSeeder::class);
    CurrentStatus::factory()->create(['name' => 'Bloqueado', 'color' => 'danger']);
    CurrentStatus::factory()->create(['name' => 'Activo', 'color' => 'success']);

    $data = screensGet(screensToken(), 'catalogs/status')->assertOk()->json('data');
    $form = CatalogForm::query()->where('component', 'catalog.status')->firstOrFail();

    expect($data['title'])->toBe($form->title)
        ->and($data['description'])->toBe($form->description)
        ->and($data['icon'])->toBe($form->icon)
        ->and($data['columns'])->toBe(array_map(fn (CatalogColumnDto $column): array => $column->toArray(), (new CurrentStatus)->catalogColumns()))
        ->and(array_column($data['columns'], 'label'))->toBe(['Nombre', 'Color'])
        ->and(array_column($data['columns'], 'cell'))->toBe(['name', 'tag'])
        // The web's own order (by name) and nothing but the id and the columns' keys.
        ->and(array_column($data['rows'], 'name'))->toBe(['Activo', 'Bloqueado'])
        ->and(array_keys($data['rows'][0]))->toBe(['id', 'name', 'color'])
        ->and($data['rows'][1]['color'])->toBe('danger')
        ->and($data['count'])->toBe(2)
        ->and($data['unit'])->toBe(__('catalog.status.plural'))
        ->and($data['search'])->toBe(['label' => __('catalog.status.search_label'), 'placeholder' => __('catalog.status.search_placeholder'), 'value' => ''])
        ->and($data['empty'])->toBe(__('catalog.status.empty'))
        ->and($data['has_more'])->toBeFalse();
});

test('Metas de mención reads the goal column from the words, not from the bare number', function (): void {
    $this->seed(CatalogFormSeeder::class);
    MentionGoal::factory()->create(['threshold' => 100]);

    $data = screensGet(screensToken(), 'catalogs/mention-goal')->assertOk()->json('data');

    expect(array_column($data['columns'], 'key'))->toBe(['achievement', 'caption', 'active'])
        ->and($data['rows'][0]['achievement'])->toBe(MentionGoal::achievement(100))
        ->and($data['rows'][0])->not->toHaveKey('threshold')
        ->and($data['columns'][2]['on'])->toBe(__('catalog.mention_goal.status.active'))
        ->and($data['columns'][2]['off'])->toBe(__('catalog.mention_goal.status.inactive'))
        ->and($data['unit'])->toBe(__('catalog.mention_goal.singular'));
});

test('Provincias pages in 25s and says when there is no more', function (): void {
    $this->seed(CatalogFormSeeder::class);
    Province::factory()->count(30)->create();
    $token = screensToken();

    $first = screensGet($token, 'catalogs/province')->assertOk()->json('data');
    $second = screensGet($token, 'catalogs/province?page=2')->assertOk()->json('data');

    expect($first['rows'])->toHaveCount(25)
        ->and($first['count'])->toBe(30)
        ->and($first['has_more'])->toBeTrue()
        ->and($second['rows'])->toHaveCount(30)
        ->and($second['has_more'])->toBeFalse()
        ->and($first['unit'])->toBe(__('catalog.province.plural'));
});

test('Monedas, Condiciones fiscales and Redes sociales serve the web\'s cell kinds and search only where the web\'s box does', function (): void {
    $this->seed(CatalogFormSeeder::class);
    // Values no factory text can contain, so a search that must find nothing cannot match a random row.
    Currency::factory()->create(['code' => 'QVS', 'name' => 'Bolívar', 'symbol' => 'ZQ$', 'decimal_places' => 2]);
    TaxCondition::factory()->create(['code' => 'QRI', 'name' => 'Responsable inscripto', 'discriminate_tax' => true]);
    SocialNetwork::factory()->create(['name' => 'Instagram', 'abbreviation' => 'QX', 'url' => 'https://zzurl.example/']);
    $token = screensToken();

    $currency = screensGet($token, 'catalogs/currency')->assertOk()->json('data');
    $tax = screensGet($token, 'catalogs/tax-condition')->assertOk()->json('data');
    $social = screensGet($token, 'catalogs/social-network')->assertOk()->json('data');

    // The symbol, the tax condition's country and a network's address and icon are the web's `catalog-cell-sym`: monospace.
    expect(array_column($currency['columns'], 'cell'))->toBe(['code', 'name', 'symbol', 'number', 'active'])
        ->and(collect($currency['rows'])->firstWhere('code', 'QVS'))->toBe(['id' => collect($currency['rows'])->firstWhere('code', 'QVS')['id'], 'code' => 'QVS', 'name' => 'Bolívar', 'symbol' => 'ZQ$', 'decimals' => 2, 'active' => true])
        ->and(array_column($tax['columns'], 'cell'))->toBe(['code', 'name', 'symbol', 'flag', 'active'])
        // A flag says yes or no in words, as the web's table does for "discriminates tax"; it has no status dot.
        ->and($tax['columns'][3]['on'])->toBe(__('catalog.tax_condition.discriminate.yes'))
        ->and($tax['columns'][3]['off'])->toBe(__('catalog.tax_condition.discriminate.no'))
        ->and(collect($tax['rows'])->firstWhere('code', 'QRI')['discriminates'])->toBeTrue()
        ->and(array_column($social['columns'], 'cell'))->toBe(['name', 'code', 'symbol', 'symbol', 'active']);

    // The web looks a currency up by code and name, never by symbol; a network by name and abbreviation, never by its address.
    expect(screensGet($token, 'catalogs/currency?search=qvs')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/currency?search=zq$')->json('data.count'))->toBe(0)
        ->and(screensGet($token, 'catalogs/social-network?search=qx')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/social-network?search=zzurl')->json('data.count'))->toBe(0);
});

test('Respuestas guardadas, Mensajes de adopción and Tipos de reporte serve the web\'s cells and words, and search where the web\'s box does', function (): void {
    $this->seed(CatalogFormSeeder::class);
    // Values no factory text can contain, so a search that must find nothing cannot match a random row.
    SupportReply::factory()->create(['name' => 'Saludo Qzw', 'body' => 'Gracias por escribir, Xjvb te responde.']);
    AdoptionNudge::factory()->create(['step' => AdoptionStep::Registered, 'subject' => 'Asunto Qpl', 'body' => 'Cuerpo del mensaje Zrk para la dueña.']);
    SupportTicketKind::factory()->create(['code' => 'qzk', 'name' => 'Falla Wvj', 'starting_priority' => 'high', 'runs_clock' => false, 'sort_order' => 7]);
    $token = screensToken();

    $replies = screensGet($token, 'catalogs/support-reply')->assertOk()->json('data');
    $nudges = screensGet($token, 'catalogs/adoption-nudge')->assertOk()->json('data');
    $kinds = screensGet($token, 'catalogs/support-ticket-kind')->assertOk()->json('data');

    expect(array_column($replies['columns'], 'cell'))->toBe(['name', 'text', 'active'])
        ->and(collect($replies['rows'])->firstWhere('name', 'Saludo Qzw'))->toMatchArray(['body' => 'Gracias por escribir, Xjvb te responde.', 'active' => true])
        ->and(array_column($nudges['columns'], 'cell'))->toBe(['name', 'text', 'text', 'active'])
        // The step travels as the ladder's words, never as its bare key.
        ->and($nudges['rows'][0])->toMatchArray(['stepLabel' => AdoptionStep::Registered->label(), 'subject' => 'Asunto Qpl'])
        ->and($nudges['rows'][0])->not->toHaveKey('step')
        ->and(array_column($kinds['columns'], 'cell'))->toBe(['code', 'name', 'symbol', 'symbol', 'symbol', 'active'])
        // The priority and the clock come as the web's words: the phone spells neither.
        ->and(collect($kinds['rows'])->firstWhere('code', 'qzk'))->toMatchArray([
            'name' => 'Falla Wvj',
            'priority' => SupportTicketPriority::High->label(),
            'clock' => __('catalog.support_ticket_kind.clock.none'),
            'sort_order' => 7,
        ]);

    // A reply by its name and its text; a message by its step, subject and text; a kind by its code and name only.
    expect(screensGet($token, 'catalogs/support-reply?search=qzw')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/support-reply?search=xjvb')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/adoption-nudge?search=qpl')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/adoption-nudge?search=zrk')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/adoption-nudge?search='.urlencode(AdoptionStep::Registered->label()))->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/support-ticket-kind?search=qzk')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/support-ticket-kind?search=wvj')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/support-ticket-kind?search='.urlencode(SupportTicketPriority::High->label()))->json('data.count'))->toBe(0);
});

test('Ejemplos del hero and Planes serve the web\'s cells and words, and search where the web\'s box does', function (): void {
    $this->seed([CatalogFormSeeder::class, PlanSeeder::class]);
    // Values no factory text can contain, so a search that must find nothing cannot match a random row.
    $season = SeasonalWindow::factory()->create(['name' => 'Navidad Qzv']);
    DemoTag::factory()->create(['slug' => 'qkz', 'label' => 'Taller Wvp', 'business_name' => 'Taller Xjb', 'sort_order' => 7]);
    DemoTag::factory()->create(['slug' => 'qkz', 'label' => 'Taller Wvp', 'business_name' => null, 'seasonal_window_id' => $season->id]);
    SubscriptionPlan::query()->where('code', 'premium')->update(['conversations_per_month' => 12345, 'archived_at' => now()]);
    $token = screensToken();

    $tags = screensGet($token, 'catalogs/demo-tag')->assertOk()->json('data');
    $plans = screensGet($token, 'catalogs/plan')->assertOk()->json('data');
    $rows = collect($tags['rows'])->where('slug', 'qkz')->values();

    // "Inherits" and "all year" are the model's words, and the inheriting cell says it is greyed.
    expect(array_column($tags['columns'], 'cell'))->toBe(['code', 'name', 'symbol', 'symbol', 'symbol', 'active'])
        ->and(collect($tags['columns'])->firstWhere('key', 'business_shown')['muted'])->toBe('business_inherits')
        ->and($rows->firstWhere('season_shown', __('catalog.demo_tag.status.evergreen')))->toMatchArray(['business_shown' => 'Taller Xjb', 'business_inherits' => false, 'sort_order' => 7])
        ->and($rows->firstWhere('season_shown', 'Navidad Qzv'))->toMatchArray(['business_shown' => __('catalog.demo_tag.status.inherits'), 'business_inherits' => true]);

    $negocio = collect($plans['rows'])->firstWhere('code', 'negocio');
    $premium = collect($plans['rows'])->firstWhere('code', 'premium');

    // The price, the figure as the browser grouped it and every tag with its tone: the phone spells none of them.
    expect(array_column($plans['columns'], 'cell'))->toBe(['name', 'number', 'number', 'number', 'number', 'badges'])
        ->and($plans['columns'][0]['codeBelow'])->toBe('code')
        ->and($negocio['price_label'])->toBe('US$ 79')
        ->and($negocio['conversations_label'])->toBe('1000')
        ->and($negocio['tags'])->toBe([
            ['label' => __('catalog.plan.tags.featured'), 'tone' => 'brand'],
            ['label' => __('catalog.plan.tags.trial', ['days' => 14]), 'tone' => 'info'],
        ])
        ->and($premium['conversations_label'])->toBe('12.345')
        ->and($premium['tags'])->toBe([['label' => __('catalog.plan.tags.archived'), 'tone' => 'neutral']]);

    // An example by its key, its name, its business and its season; a plan by its name and its code.
    expect(screensGet($token, 'catalogs/demo-tag?search=xjb')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/demo-tag?search=qzv')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/demo-tag?search=wvp')->json('data.count'))->toBe(2)
        ->and(screensGet($token, 'catalogs/plan?search=negocio')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/plan?search=79')->json('data.count'))->toBe(0);
});

test('the three ideas of Planes and Ejemplos del hero: what each plan brings in, the door to the landing, and a season on its way', function (): void {
    $this->seed([CatalogFormSeeder::class, PlanSeeder::class]);
    // A business opens its own subscription when it is created: that one is made to pay, as in real life.
    $paying = ['plan' => 'negocio', 'status' => SubscriptionStatus::Active, 'billing_cycle' => 'monthly'];
    Subscription::query()->where('business_id', Business::factory()->create()->id)->update($paying);
    // A landing demo on the same plan is ours: it neither counts nor brings money in. A business on its trial pays nothing.
    Subscription::query()->where('business_id', Business::factory()->create(['is_demo' => true])->id)->update($paying);
    Business::factory()->create();
    // An archived plan still charges whoever holds it: its line stays while someone pays.
    Subscription::query()->where('business_id', Business::factory()->create()->id)->update([...$paying, 'plan' => 'premium']);
    SubscriptionPlan::query()->where('code', 'premium')->update(['archived_at' => now()]);

    $soon = SeasonalWindow::factory()->create(['name' => 'Carnaval Qzv', 'is_active' => true, 'starts_at' => SeasonalWindow::today()->addDays(5), 'ends_at' => SeasonalWindow::today()->addDays(9)]);
    $off = SeasonalWindow::factory()->create(['name' => 'Pascua Wvp', 'is_active' => false, 'starts_at' => SeasonalWindow::today()->addDays(5), 'ends_at' => SeasonalWindow::today()->addDays(9)]);
    DemoTag::factory()->create(['slug' => 'qkz']);
    DemoTag::factory()->create(['slug' => 'qkz', 'seasonal_window_id' => $soon->id]);
    DemoTag::factory()->create(['slug' => 'qkw', 'seasonal_window_id' => $off->id]);
    DemoTag::factory()->create(['slug' => 'qkv', 'is_active' => false]);
    $token = screensToken();

    $lines = collect(screensGet($token, 'catalogs/plan')->assertOk()->json('data.highlights.lines'))->keyBy('when');
    $amount = fn (string $plan): string => number_format(Subscription::monthlyRecurringRevenue($plan), 2, ',', '.');

    // Those who pay, as the Inicio counts them: the trial and the demo left out, the archived plan kept while it charges.
    expect($lines[__('plan.names.negocio')]['text'])->toBe(trans_choice('catalog.plan.highlights.line', 1, ['count' => 1, 'amount' => $amount('negocio')]))
        ->and($lines[__('plan.names.premium')]['text'])->toBe(trans_choice('catalog.plan.highlights.line', 1, ['count' => 1, 'amount' => $amount('premium')]))
        ->and($lines[__('plan.names.emprende')]['text'])->toBe(trans_choice('catalog.plan.highlights.line', 0))
        ->and($lines->sum(fn (array $line): int => (int) preg_match('/pagando/', $line['text'])))->toBe(Subscription::payingCount())
        ->and(Subscription::monthlyRecurringRevenue('negocio') + Subscription::monthlyRecurringRevenue('premium'))->toBe(Subscription::monthlyRecurringRevenue());

    $rows = collect(screensGet($token, 'catalogs/demo-tag')->assertOk()->json('data.rows'));
    $yearRound = $rows->first(fn (array $row): bool => $row['slug'] === 'qkz' && $row['season_shown'] === __('catalog.demo_tag.status.evergreen'));
    $season = $rows->first(fn (array $row): bool => $row['slug'] === 'qkz' && $row !== $yearRound);

    // The door only on the hero's own pills: the year-round row that is on; never a season's row nor one that is off.
    expect($yearRound['landing_url'])->toBe(rtrim(url('/'), '/').'/?rubro=qkz')
        ->and($season['landing_url'])->toBeNull()
        ->and($rows->firstWhere('slug', 'qkv')['landing_url'])->toBeNull()
        // A countdown only for a season that is switched on: one switched off never starts.
        ->and($season['season_shown'])->toBe(trans_choice('catalog.demo_tag.status.starts_in', 5, ['name' => 'Carnaval Qzv', 'count' => 5]))
        ->and($rows->firstWhere('slug', 'qkw')['season_shown'])->toBe('Pascua Wvp');
});

test('Temporadas and WhatsApp por temporada serve the web\'s cells and words, and the web\'s switches never reach the phone', function (): void {
    $this->seed(CatalogFormSeeder::class);
    $empty = SeasonalWindow::factory()->create(['name' => 'Pascua Qzv', 'notice' => null, 'priority' => 7, 'starts_at' => SeasonalWindow::today()->addDays(30), 'ends_at' => SeasonalWindow::today()->addDays(32)]);
    $busy = SeasonalWindow::factory()->create(['name' => 'Verano Xjb', 'notice' => 'Atendemos hasta las 14', 'is_active' => true, 'starts_at' => SeasonalWindow::today()->subDay(), 'ends_at' => SeasonalWindow::today()->addDays(3)]);
    DemoTag::factory()->count(2)->create(['seasonal_window_id' => $busy->id]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $busy->id, 'phone' => null, 'opening_schedule' => null, 'is_active' => true]);
    Company::factory()->create(['phone' => '+58-0412-5550199', 'opening_schedule' => null]);
    $token = screensToken();

    $windows = screensGet($token, 'catalogs/seasonal-window')->assertOk()->json('data');
    $variants = screensGet($token, 'catalogs/whatsapp-variant')->assertOk()->json('data');

    // "Repetir" is a button of the web: the read-only phone never receives the column.
    expect(array_column($windows['columns'], 'cell'))->toBe(['name', 'symbol', 'symbol', 'symbol', 'active', 'active'])
        ->and(array_column($windows['columns'], 'key'))->not->toContain('id')
        ->and($windows['columns'][0])->toMatchArray(['note' => 'content_shown', 'noteWarning' => 'content_empty'])
        ->and(collect($windows['rows'])->firstWhere('name', 'Pascua Qzv'))->toMatchArray(['content_shown' => __('catalog.seasonal_window.content.none'), 'content_empty' => true, 'priority' => 7])
        ->and(collect($windows['rows'])->firstWhere('name', 'Verano Xjb'))->toMatchArray([
            'content_shown' => __('catalog.seasonal_window.content.some', ['parts' => __('catalog.seasonal_window.content.notice').' · '.__('catalog.seasonal_window.content.hero_many', ['count' => 2]).' · '.__('catalog.seasonal_window.content.whatsapp_one')]),
            'content_empty' => false,
            'running' => true,
        ]);

    // The season the landing speaks with today and its calendar days left; the one still to come is not it.
    expect($windows['highlights'])->toBe([
        'title' => __('catalog.seasonal_window.highlights_title'),
        'lines' => [['when' => 'Verano Xjb', 'text' => trans_choice('catalog.seasonal_window.highlights.ends', 3, ['count' => 3, 'date' => SeasonalWindow::today()->addDays(3)->format('d/m/Y')]), 'mono' => false]],
    ]);

    // Blank inherits from the company row: said in words and greyed, and the line under the season names what it
    // inherits; the company has no week, so only its number is named.
    // The season's dates ride under its name (`sub`), not as columns: with its own number and week a row fits at 1280.
    expect(array_column($variants['columns'], 'cell'))->toBe(['name', 'symbol', 'symbol', 'active', 'active'])
        ->and($variants['columns'][0]['sub'])->toBe('dates')
        ->and($variants['rows'][0]['dates'])->toBe(__('catalog.whatsapp_variant.dates', ['from' => SeasonalWindow::today()->subDay()->format('d/m/Y'), 'to' => SeasonalWindow::today()->addDays(3)->format('d/m/Y')]))
        ->and($variants['columns'][0]['note'])->toBe('inherits_note')
        ->and($variants['columns'][0]['link'])->toBe(['key' => 'company_url', 'label' => __('catalog.whatsapp_variant.inherits.edit')])
        ->and($variants['rows'][0]['company_url'])->toBe(route('admin.company'))
        ->and($variants['rows'][0])->toMatchArray([
            'season' => 'Verano Xjb',
            'inherits_note' => __('catalog.whatsapp_variant.inherits.note', ['parts' => __('catalog.whatsapp_variant.inherits.phone', ['value' => '+58-0412-5550199'])]),
            'phone_shown' => __('catalog.whatsapp_variant.status.inherits'),
            'phone_inherits' => true,
            'hours_shown' => __('catalog.whatsapp_variant.status.inherits'),
            'hours_inherits' => true,
        ]);

    expect(screensGet($token, 'catalogs/seasonal-window?search=qzv')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/whatsapp-variant?search=xjb')->json('data.count'))->toBe(1);
});

test('Soporte\'s three ideas: a kind\'s glyph beside its name, the copy words of a reply, and each message as it reaches a real account', function (): void {
    $this->seed(CatalogFormSeeder::class);
    SupportTicketKind::factory()->create(['code' => 'qzk', 'name' => 'Falla Wvj', 'icon' => 'life-buoy']);
    SupportReply::factory()->create(['name' => 'Saludo Qzw']);
    AdoptionNudge::factory()->create(['step' => AdoptionStep::Registered, 'body' => "Hola {nombre},\n\nya casi está {negocio}."]);
    AdoptionNudge::factory()->create(['step' => AdoptionStep::BusinessCreated, 'body' => 'Apagado {nombre}.', 'is_active' => false]);
    $token = screensToken();

    // Nobody to show it to yet, or only someone who has not made a business: no lines rather than "Ya creaste , y…".
    expect(screensGet($token, 'catalogs/adoption-nudge')->json('data.highlights.lines'))->toBe([]);
    User::factory()->create(['name' => 'Sin Negocio', 'business_id' => null])->assignRole('client');
    expect(screensGet($token, 'catalogs/adoption-nudge')->json('data.highlights.lines'))->toBe([]);

    $owner = User::factory()->for(Business::factory()->create(['name' => 'Panadería Qxv']))->create(['name' => 'Ana Zelk']);
    $owner->assignRole('client');
    // A newer signup still in the wizard does not take the preview over.
    User::factory()->create(['name' => 'Recién Llegada', 'business_id' => null])->assignRole('client');

    $kinds = screensGet($token, 'catalogs/support-ticket-kind')->assertOk()->json('data');
    $replies = screensGet($token, 'catalogs/support-reply')->assertOk()->json('data');
    $nudges = screensGet($token, 'catalogs/adoption-nudge')->assertOk()->json('data');
    $kind = collect($kinds['rows'])->firstWhere('code', 'qzk');

    // The glyph travels by its key; the web's own SVG markup never reaches the phone.
    expect($kinds['columns'][1]['icon'])->toBe('icon')
        ->and($kind['icon'])->toBe('life-buoy')
        ->and($kind)->not->toHaveKey('icon_svg')
        ->and($replies['columns'][1]['copy'])->toBe(['label' => __('catalog.support_reply.copy.label'), 'done' => __('catalog.support_reply.copy.done')])
        ->and($replies['columns'][0]['copy'])->toBeNull()
        ->and($nudges['highlights']['title'])->toBe(__('catalog.adoption_nudge.highlights_title'))
        // Only the active message, its blanks filled with the real account and its paragraphs folded.
        ->and($nudges['highlights']['lines'])->toBe([
            ['when' => AdoptionStep::Registered->label(), 'text' => 'Hola Ana Zelk, ya casi está Panadería Qxv.'],
        ]);

    // A brand logo is filled on the web, as `<x-icon>` draws it; a stroked glyph is not; a key gone from the registry draws nothing.
    $glyphs = collect([['brand', 'whatsapp'], ['stroked', 'life-buoy'], ['gone', 'qzx-gone']])
        ->mapWithKeys(fn (array $pair): array => [$pair[0] => SupportTicketKind::factory()->make(['icon' => $pair[1]])])
        ->each(fn (SupportTicketKind $kind) => $kind->saveQuietly());
    $rows = (new SupportTicketKind)->catalogRows()->keyBy('icon');

    expect($rows['whatsapp'])->toMatchArray(['icon_filled' => true])
        ->and($rows['whatsapp']['icon_svg'])->not->toBe('')
        ->and($rows['life-buoy'])->toMatchArray(['icon_filled' => false])
        ->and($rows['qzx-gone'])->toMatchArray(['icon_svg' => '', 'icon_filled' => false])
        ->and($glyphs)->toHaveCount(3);
});

test('Países, Regiones and Feriados serve the web\'s cells, the kind of a holiday as its badge, and search where the web\'s box does', function (): void {
    $this->seed(CatalogFormSeeder::class);
    // Values no factory text can contain, so a search that must find nothing cannot match a random row.
    $country = Country::factory()->create(['code' => 'QZV', 'iso2' => 'QZ', 'name' => 'Zuliandia', 'phone_code' => '+958', 'currency_id' => Currency::factory()->create(['code' => 'QZB'])->id]);
    Region::factory()->for(Province::factory()->for($country)->create(['name' => 'Provincia Qwy']))->create(['name' => 'Valle Xjk']);
    CountryHoliday::create(['country_id' => $country->id, 'name' => 'Carnaval Qpo', 'easter_offset' => -48]);
    CountryHoliday::create(['country_id' => $country->id, 'name' => 'Puente Qpa', 'on_date' => '2026-12-07']);
    $token = screensToken();

    $countries = screensGet($token, 'catalogs/country')->assertOk()->json('data');
    $regions = screensGet($token, 'catalogs/region')->assertOk()->json('data');
    $holidays = screensGet($token, 'catalogs/country-holiday')->assertOk()->json('data');
    $easter = collect($holidays['rows'])->firstWhere('name', 'Carnaval Qpo');

    expect(array_column($countries['columns'], 'cell'))->toBe(['code', 'code', 'name', 'symbol', 'symbol', 'number', 'number', 'active'])
        ->and(collect($countries['rows'])->firstWhere('code', 'QZV'))->toMatchArray(['iso2' => 'QZ', 'name' => 'Zuliandia', 'phone_code' => '+958', 'currency' => 'QZB', 'provinces' => 1, 'holidays' => 2, 'active' => true])
        ->and(array_column($regions['columns'], 'cell'))->toBe(['name', 'text', 'text', 'active'])
        ->and(collect($regions['rows'])->firstWhere('name', 'Valle Xjk'))->toMatchArray(['province' => 'Provincia Qwy', 'country' => 'Zuliandia'])
        ->and(array_column($holidays['columns'], 'cell'))->toBe(['text', 'name', 'symbol', 'badge', 'active'])
        // The kind travels as its key; the column says the web's word and tone for each one.
        ->and($easter['kind'])->toBe(CountryHoliday::KIND_EASTER)
        ->and($easter['when'])->toBe(CountryHoliday::query()->where('name', 'Carnaval Qpo')->firstOrFail()->describeWhen())
        ->and($holidays['columns'][3]['badges'])->toBe([
            'fixed' => ['label' => __('catalog.country_holiday.kinds.fixed'), 'tone' => 'neutral'],
            'easter' => ['label' => __('catalog.country_holiday.kinds.easter'), 'tone' => 'info'],
            'nth' => ['label' => __('catalog.country_holiday.kinds.nth'), 'tone' => 'neutral'],
            'once' => ['label' => __('catalog.country_holiday.kinds.once'), 'tone' => 'brand'],
        ])
        ->and($countries['columns'][0]['badges'])->toBeNull();

    // A country by its 3-letter code and its name, never by the 2-letter one, its phone code or its currency.
    expect(screensGet($token, 'catalogs/country?search=qzv')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/country?search=zulian')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/country?search=qzb')->json('data.count'))->toBe(0)
        ->and(screensGet($token, 'catalogs/country?search=958')->json('data.count'))->toBe(0)
        // A region by its own name, its province or its country.
        ->and(screensGet($token, 'catalogs/region?search=xjk')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/region?search=qwy')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/region?search=zuliandia')->json('data.count'))->toBe(1)
        // A holiday by its country and its name, never by the word of its kind.
        ->and(screensGet($token, 'catalogs/country-holiday?search=qpa')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/country-holiday?search=zuliandia')->json('data.count'))->toBe(2)
        ->and(screensGet($token, 'catalogs/country-holiday?search=easter')->json('data.count'))->toBe(0);
});

test('Feriados and Regiones offer the web\'s country chips, Feriados its next holiday per country, Países its counts', function (): void {
    $this->seed(CatalogFormSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00', 'America/Caracas'));
    // Values no factory text can contain; "Ázkaria" sorts with the A's although its first letter has an accent.
    $zul = Country::factory()->create(['name' => 'Zuliandia']);
    $azk = Country::factory()->create(['name' => 'Ázkaria']);
    CountryHoliday::create(['country_id' => $zul->id, 'name' => 'Navidad Qz', 'month' => 12, 'day' => 25]);
    CountryHoliday::create(['country_id' => $zul->id, 'name' => 'Año Nuevo Qz', 'month' => 1, 'day' => 1]);
    CountryHoliday::create(['country_id' => $azk->id, 'name' => 'Fiesta Qa', 'month' => 1, 'day' => 6]);
    CountryHoliday::create(['country_id' => $azk->id, 'name' => 'Apagado Qa', 'month' => 11, 'day' => 2, 'is_active' => false]);
    Province::factory()->count(2)->for($zul)->create();
    $token = screensToken();

    $all = screensGet($token, 'catalogs/country-holiday')->assertOk()->json('data');
    $zulOnly = screensGet($token, 'catalogs/country-holiday?chip=Zuliandia')->assertOk()->json('data');
    $unknown = screensGet($token, 'catalogs/country-holiday?chip=Nadaland')->assertOk()->json('data');

    expect($all['chips'])->toBe(['label' => __('catalog.hub.chips_label'), 'all' => __('catalog.hub.chips_all'), 'placeholder' => __('catalog.hub.chips_placeholder'), 'select' => false, 'value' => '', 'options' => ['Ázkaria', 'Zuliandia']])
        ->and($zulOnly['chips']['value'])->toBe('Zuliandia')
        ->and($zulOnly['count'])->toBe(2)
        ->and(array_unique(array_column($zulOnly['rows'], 'country')))->toBe(['Zuliandia'])
        // A chip that names no country reads as "all", as any unknown filter does.
        ->and($unknown['chips']['value'])->toBe('')
        ->and($unknown['count'])->toBe(4)
        // The chips stay whole under a search, and the search works inside a chip.
        ->and(screensGet($token, 'catalogs/country-holiday?chip=Zuliandia&search=navidad')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/country-holiday?search=fiesta')->json('data.chips.options'))->toBe(['Ázkaria', 'Zuliandia']);

    // The next ACTIVE day from today, in the platform's clock: a year with nothing left looks into the next one.
    expect($all['highlights'])->toBe([
        'title' => __('catalog.country_holiday.highlights_title'),
        'lines' => [
            ['when' => '06/01/2027', 'text' => __('catalog.country_holiday.upcoming.line', ['country' => 'Ázkaria', 'name' => 'Fiesta Qa'])],
            ['when' => '25/12/2026', 'text' => __('catalog.country_holiday.upcoming.line', ['country' => 'Zuliandia', 'name' => 'Navidad Qz'])],
        ],
    ]);

    // Regiones filter by country too; a master without a chip column has no chips and nothing to highlight.
    $regions = screensGet($token, 'catalogs/region')->assertOk()->json('data');
    $countries = screensGet($token, 'catalogs/country')->assertOk()->json('data');

    // Past CHIPS_MAX countries the same filter becomes one selector, on the web and on the phone.
    Region::factory()->count(CatalogColumnDto::CHIPS_MAX + 1)->create();
    expect(screensGet($token, 'catalogs/region')->json('data.chips.select'))->toBeTrue();

    expect($regions['chips']['select'])->toBeFalse()
        ->and($regions['highlights'])->toBeNull()
        ->and($countries['chips'])->toBeNull()
        ->and($countries['highlights'])->toBeNull()
        ->and(collect($countries['rows'])->firstWhere('name', 'Zuliandia'))->toMatchArray(['provinces' => 2, 'holidays' => 2])
        // An inactive holiday is still a holiday the country has: the list counts every row it shows.
        ->and(collect($countries['rows'])->firstWhere('name', 'Ázkaria'))->toMatchArray(['provinces' => 0, 'holidays' => 2])
        ->and(array_column($countries['columns'], 'cell'))->toBe(['code', 'code', 'name', 'symbol', 'symbol', 'number', 'number', 'active']);
});

test('on the holiday itself, with the platform in Caracas, the next holiday is today\'s, not the one after', function (): void {
    $this->seed(CatalogFormSeeder::class);
    // The platform's clock comes from the company's country: Venezuela is four hours behind the UTC the dates are built in.
    $venezuela = Country::factory()->create(['name' => 'Zuliandia', 'iso2' => 'VE']);
    Company::factory()->create(['region_id' => Region::factory()->for(Province::factory()->for($venezuela))]);
    CountryHoliday::create(['country_id' => $venezuela->id, 'name' => 'Año Nuevo Qz', 'month' => 1, 'day' => 1]);
    CountryHoliday::create(['country_id' => $venezuela->id, 'name' => 'Fiesta Qz', 'month' => 2, 'day' => 8]);

    // Asked of the model the web and the API both read, across the day: a staff session would not survive the jumps.
    $next = function (string $moment): array {
        $this->travelTo(CarbonImmutable::parse($moment, 'America/Caracas'));

        return (new CountryHoliday)->catalogHighlights()[0];
    };

    // The evening before, in Caracas, it is still the 31st: the next one is tomorrow's.
    expect($next('2026-12-31 22:00')['when'])->toBe('01/01/2027');

    foreach (['2027-01-01 00:30', '2027-01-01 12:00', '2027-01-01 23:30'] as $moment) {
        expect($next($moment))->toBe(['when' => '01/01/2027', 'text' => __('catalog.country_holiday.upcoming.line', ['country' => 'Zuliandia', 'name' => 'Año Nuevo Qz'])]);
    }

    // And the day after, it moves on.
    expect($next('2027-01-02 09:00')['when'])->toBe('08/02/2027');
});

test('Negocio and Servicios serve a name with its line under it, its tag and glyph, and lists as pills, searched as the web\'s box', function (): void {
    $this->seed(CatalogFormSeeder::class);
    // Values no factory text can contain, so a search that must find nothing cannot match a random row.
    $sector = BusinessSector::factory()->create(['name' => 'Rubro Qx']);
    BusinessActivity::factory()->create(['code' => 'act-qx', 'name' => 'Oficio Qx', 'business_sector_id' => $sector->id]);
    $modality = ServiceModality::factory()->create(['code' => 'mod-qm', 'name' => 'Modo Qm', 'description' => 'Pregunta zzqm', 'icon' => 'heart']);
    $type = ServiceType::factory()->create(['code' => 'tipo-qt', 'name' => 'Tipo Qt', 'description' => 'Aclara zzqt', 'service_modality_id' => $modality->id, 'business_sector_id' => $sector->id]);
    $attribute = ServiceAttribute::factory()->create(['code' => 'atr-qa', 'name' => 'Talle Qa', 'description' => 'Explica zzqa', 'data_type' => 'list', 'options' => ['Chico Qa', 'Grande Qa']]);
    $type->serviceAttributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 1, 'label_override' => 'Medida Qa']);
    $token = screensToken();

    $types = screensGet($token, 'catalogs/service-type')->assertOk()->json('data');
    $attributes = screensGet($token, 'catalogs/service-attribute')->assertOk()->json('data');
    $modalities = screensGet($token, 'catalogs/service-modality')->assertOk()->json('data');
    $activities = screensGet($token, 'catalogs/business-activity')->assertOk()->json('data');

    // The name brings its line, its tag and its glyph; the row carries those keys and nothing else extra.
    expect(collect($types['columns'])->firstWhere('key', 'name'))->toMatchArray(['cell' => 'name', 'sub' => 'description', 'tag' => 'sector', 'icon' => null])
        ->and(array_column($types['columns'], 'cell'))->toBe(['code', 'name', 'text', 'list', 'active'])
        ->and(collect($types['rows'])->firstWhere('code', 'tipo-qt'))->toMatchArray(['description' => 'Aclara zzqt', 'sector' => 'Rubro Qx', 'modality' => 'Modo Qm', 'attributes' => ['Medida Qa']])
        ->and(collect($modalities['columns'])->firstWhere('key', 'name'))->toMatchArray(['sub' => 'description', 'icon' => 'icon'])
        ->and(collect($modalities['rows'])->firstWhere('code', 'mod-qm'))->toMatchArray(['icon' => 'heart', 'description' => 'Pregunta zzqm'])
        ->and(collect($modalities['rows'])->firstWhere('code', 'mod-qm'))->not->toHaveKey('icon_svg')
        ->and(array_column($attributes['columns'], 'cell'))->toBe(['code', 'name', 'text', 'list', 'active'])
        ->and(collect($attributes['rows'])->firstWhere('code', 'atr-qa')['options'])->toBe(['Chico Qa', 'Grande Qa'])
        ->and(array_column($activities['columns'], 'cell'))->toBe(['code', 'name', 'text', 'symbol', 'active']);

    // Each box looks where the web's did: a type by its sector and its attributes, never by its description;
    // an attribute and a modality by their description; an attribute never by its options.
    expect(screensGet($token, 'catalogs/service-type?search=rubro qx')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/service-type?search=medida qa')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/service-type?search=zzqt')->json('data.count'))->toBe(0)
        ->and(screensGet($token, 'catalogs/service-attribute?search=zzqa')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/service-attribute?search=grande qa')->json('data.count'))->toBe(0)
        ->and(screensGet($token, 'catalogs/service-modality?search=zzqm')->json('data.count'))->toBe(1)
        ->and(screensGet($token, 'catalogs/business-activity?search=rubro qx')->json('data.count'))->toBe(1);
});

test('Feriados lists each country\'s days as this year\'s calendar reads them, the countries in the chips\' order', function (): void {
    $this->seed(CatalogFormSeeder::class);
    // Easter 2026 is 5 April: Carnaval lands in February and Viernes Santo on 3 April.
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00', 'America/Caracas'));
    $zul = Country::factory()->create(['name' => 'Zuliandia']);
    $azk = Country::factory()->create(['name' => 'Ázkaria']);
    foreach ([['Viernes Qz', ['easter_offset' => -2]], ['Malvinas Qz', ['month' => 4, 'day' => 2]], ['Carnaval Qz', ['easter_offset' => -48]], ['Memoria Qz', ['month' => 3, 'day' => 24]], ['Año Qz', ['month' => 1, 'day' => 1]]] as [$name, $when]) {
        CountryHoliday::create(['country_id' => $zul->id, 'name' => $name, ...$when]);
    }
    CountryHoliday::create(['country_id' => $azk->id, 'name' => 'Fiesta Qa', 'month' => 12, 'day' => 1]);

    $rows = (new CountryHoliday)->catalogRows();

    expect($rows->pluck('name')->all())->toBe(['Fiesta Qa', 'Año Qz', 'Carnaval Qz', 'Memoria Qz', 'Malvinas Qz', 'Viernes Qz'])
        ->and($rows->pluck('country')->unique()->values()->all())->toBe(['Ázkaria', 'Zuliandia']);
});

test('the search is the editor\'s box: trimmed, any case, contained in a name or a text, never in a tag or a switch', function (): void {
    $this->seed(CatalogFormSeeder::class);
    $venezuela = Country::factory()->create(['name' => 'Venezuela']);
    Province::factory()->for($venezuela)->create(['name' => 'Mérida']);
    Province::factory()->create(['name' => 'Córdoba']);
    $token = screensToken();

    // By name, and by the country column, with the web's lowercase-and-contains.
    $byName = screensGet($token, 'catalogs/province?search='.rawurlencode('  MÉRI '))->assertOk()->json('data');
    $byCountry = screensGet($token, 'catalogs/province?search=venezu')->assertOk()->json('data');

    expect(array_column($byName['rows'], 'name'))->toBe(['Mérida'])
        ->and($byName['count'])->toBe(1)
        ->and($byName['unit'])->toBe(__('catalog.province.singular'))
        ->and($byName['search']['value'])->toBe('MÉRI')
        ->and(array_column($byCountry['rows'], 'name'))->toBe(['Mérida']);

    // A status is found by its name, not by the colour its tag is painted.
    CurrentStatus::factory()->create(['name' => 'Activo', 'color' => 'success']);
    expect(screensGet($token, 'catalogs/status?search=success')->assertOk()->json('data.rows'))->toBe([]);

    // Nothing found says it in the web's words.
    expect(screensGet($token, 'catalogs/status?search=zzzz')->json('data.empty'))->toBe(__('catalog.status.empty'));
});

test('odd inputs read as nothing: a list for a search, a page beyond the last, a master that is not one', function (): void {
    $this->seed(CatalogFormSeeder::class);
    $token = screensToken();

    // `search[]=` is an array: cast it and it is a 500; read as no search it answers like the plain list.
    screensGet($token, 'catalogs/status?search[]=x')->assertOk()->assertJsonPath('data.search.value', '');
    screensGet($token, 'catalogs/status?page=-4')->assertOk();
    screensGet($token, 'catalogs/status?page=999999')->assertOk();

    // A master the web has not, and a shape that is no slug at all (the app reads all 21 the web has).
    screensGet($token, 'catalogs/nonsense')->assertNotFound();
    screensGet($token, 'catalogs/Status_1')->assertNotFound();
});

test('with the base empty the hub and a master answer, and say there is nothing, never a 500', function (): void {
    $token = screensToken();

    expect(screensGet($token, 'catalogs')->assertOk()->json('data.groups'))->toBe([]);
    screensGet($token, 'catalogs/status')->assertNotFound();

    $this->seed(CatalogFormSeeder::class);
    $empty = screensGet($token, 'catalogs/status')->assertOk()->json('data');

    expect($empty['rows'])->toBe([])->and($empty['count'])->toBe(0)->and($empty['has_more'])->toBeFalse();
});
