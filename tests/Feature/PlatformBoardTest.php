<?php

declare(strict_types=1);

use App\Actions\Admin\NotifyPlatformAdmin;
use App\Actions\Admin\ReconcilePlatformEvents;
use App\Actions\Admin\SendPlatformDigest;
use App\Actions\Admin\SweepPlatform;
use App\Dto\SensorReadingDto;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventState;
use App\Enums\PlatformSensorStatus as Status;
use App\Enums\SubscriptionStatus;
use App\Jobs\PlatformHeartbeat;
use App\Models\Business;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\PlatformSensorReading;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CertificateReader;
use App\Services\EvolutionApi;
use App\Services\PlatformSensors;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The admin's glance board
|--------------------------------------------------------------------------
| The sweep reads sensors, tells the feed what changed and phones the admin
| about what is critical and unseen. The screen is the door to all of it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Bus::fake([PlatformHeartbeat::class]);
});

/** Replaces what the sweep reads with a fixed list, one look at a time. */
function lookAs(Status $status, string $key = 'disk', string $group = 'system'): void
{
    app()->instance(PlatformSensors::class, new class($status, $key, $group) extends PlatformSensors
    {
        public function __construct(private Status $status, private string $key, private string $group) {}

        public function read(): array
        {
            return [new SensorReadingDto($this->key, $this->group, $this->status, '99 %', 'detail')];
        }
    });
}

function adminWithPhone(): User
{
    $admin = User::factory()->create(['whatsapp' => '+58 414 5550000']);
    $admin->syncRoles('admin');

    return $admin;
}

test('a sweep stores what each sensor says and sends the queue heartbeat', function (): void {
    lookAs(Status::Warn);

    app(SweepPlatform::class)->handle();

    $sensor = PlatformSensor::query()->sole();

    expect($sensor->status)->toBe(Status::Warn)
        ->and($sensor->value)->toBe('99 %')
        ->and($sensor->failures)->toBe(1);
    Bus::assertDispatched(PlatformHeartbeat::class);
});

test('one bad look is a blip and the second in a row tells the feed', function (): void {
    lookAs(Status::Crit, 'redis');

    app(SweepPlatform::class)->handle();
    expect(PlatformEvent::query()->count())->toBe(0);

    app(SweepPlatform::class)->handle();

    $line = PlatformEvent::query()->sole();
    expect($line->kind)->toBe(PlatformEventKind::SystemDown)
        ->and($line->severity->value)->toBe('critical')
        ->and($line->dedupe_key)->toBe('sensor:redis:crit');

    // Still down: the same line, not a new one every two minutes.
    app(SweepPlatform::class)->handle();
    expect(PlatformEvent::query()->count())->toBe(1);
});

test('a clean look closes the line by itself', function (): void {
    lookAs(Status::Crit, 'redis');
    app(SweepPlatform::class)->handle();
    app(SweepPlatform::class)->handle();

    lookAs(Status::Ok, 'redis');
    app(SweepPlatform::class)->handle();

    expect(PlatformEvent::query()->sole()->state)->toBe(PlatformEventState::Resolved);
});

test('a reading nobody refreshed is shown as unknown, never as green', function (): void {
    PlatformSensor::query()->create([
        'sensor_key' => 'queue', 'group' => 'system', 'status' => 'ok',
        'checked_at' => now()->subMinutes(30), 'changed_at' => now()->subMinutes(30),
    ]);

    expect(PlatformSensor::query()->sole()->shownStatus())->toBe(Status::Unknown)
        ->and(PlatformSensor::watcherIsQuiet())->toBeTrue()
        ->and(PlatformSensor::summary()['ok'])->toBe(0);
});

test('reconciling raises a line for a condition that predates the feed and closes it when it clears', function (): void {
    $business = Business::factory()->create(['is_demo' => false]);
    $subscription = Subscription::factory()->create(['business_id' => $business->id]);
    $subscription->forceFill(['status' => SubscriptionStatus::Paused])->save();

    app(ReconcilePlatformEvents::class)->handle();

    $line = PlatformEvent::query()->where('kind', PlatformEventKind::PaymentPaused->value)->sole();
    expect($line->about_business_id)->toBe($business->id)
        ->and($line->severity->value)->toBe('critical');

    // A line somebody already saw keeps its state and its count.
    $line->acknowledge(adminWithPhone());
    app(ReconcilePlatformEvents::class)->handle();
    expect($line->fresh()->state)->toBe(PlatformEventState::Acknowledged)
        ->and($line->fresh()->occurrences)->toBe(1);

    $subscription->forceFill(['status' => SubscriptionStatus::Active])->save();
    app(ReconcilePlatformEvents::class)->handle();

    expect($line->fresh()->state)->toBe(PlatformEventState::Resolved);
});

test('only an unseen critical reaches the admin phone, once, and again after the wait', function (): void {
    adminWithPhone();
    $sent = [];
    $this->mock(EvolutionApi::class, function ($mock) use (&$sent): void {
        $mock->shouldReceive('sendText')->andReturnUsing(function (string $instance, string $number, string $text) use (&$sent): string {
            $sent[] = $text;

            return 'id';
        });
    });

    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentLate)->create();
    $critical = PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();

    expect(app(NotifyPlatformAdmin::class)->handle())->toBe(1);
    expect(app(NotifyPlatformAdmin::class)->handle())->toBe(0);
    expect($sent)->toHaveCount(1)
        ->and($sent[0])->toContain($critical->kind->label());

    $this->travel(31)->minutes();
    expect(app(NotifyPlatformAdmin::class)->handle())->toBe(1);

    $critical->acknowledge(User::query()->first());
    $this->travel(31)->minutes();
    expect(app(NotifyPlatformAdmin::class)->handle())->toBe(0);
});

test('a client cannot open the board and an admin can', function (): void {
    $client = User::factory()->create();
    $this->actingAs($client)->get(route('admin.alerts'))->assertForbidden();

    $this->actingAs(adminWithPhone())->get(route('admin.alerts'))
        ->assertOk()
        ->assertSee(__('platform_events.screen.title'));
});

test('the board shows the open lines worst first and an admin marks one seen', function (): void {
    $admin = adminWithPhone();
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessJoined)->create();
    $critical = PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();

    Livewire::actingAs($admin)->test('admin.alerts.index')
        ->assertSeeInOrder([PlatformEventKind::PaymentPaused->label(), PlatformEventKind::BusinessJoined->label()])
        ->call('acknowledge', $critical->id);

    expect($critical->fresh()->state)->toBe(PlatformEventState::Acknowledged)
        ->and($critical->fresh()->acknowledged_by)->toBe($admin->id);
});

test('the history strip keeps the worst look of each hour and says nothing where nobody looked', function (): void {
    $this->travelTo(now()->startOfHour()->addMinutes(30));

    foreach ([[Status::Ok, 10], [Status::Crit, 5], [Status::Ok, 90]] as [$status, $minutesAgo]) {
        PlatformSensorReading::query()->create(['sensor_key' => 'disk', 'status' => $status, 'checked_at' => now()->subMinutes($minutesAgo)]);
    }

    $strip = PlatformSensorReading::strips(['disk'])['disk'];

    expect($strip)->toHaveCount(24)
        ->and($strip[23])->toBe(Status::Crit)
        ->and($strip[22])->toBe(Status::Ok)
        ->and($strip[0])->toBeNull();
});

test('a sweep keeps one reading per infrastructure sensor and none for business ones', function (): void {
    lookAs(Status::Ok, 'disk', 'system');
    app(SweepPlatform::class)->handle();

    lookAs(Status::Ok, 'receipts', 'money');
    app(SweepPlatform::class)->handle();

    expect(PlatformSensorReading::query()->pluck('sensor_key')->all())->toBe(['disk']);
});

test('a component can be copied as a diagnostic a technician can paste', function (): void {
    lookAs(Status::Crit, 'disk', 'system');
    app(SweepPlatform::class)->handle();

    $text = PlatformSensor::query()->sole()->diagnostic();

    expect($text)->toContain(__('platform_events.sensors.disk.name'))
        ->toContain('disk')
        ->toContain(__('platform_events.screen.status_crit'));
});

test('the evening digest goes out only when something is waiting, and names it', function (): void {
    adminWithPhone();
    $sent = [];
    $this->mock(EvolutionApi::class, function ($mock) use (&$sent): void {
        $mock->shouldReceive('sendText')->andReturnUsing(function (string $instance, string $number, string $text) use (&$sent): string {
            $sent[] = $text;

            return 'id';
        });
    });

    expect(app(SendPlatformDigest::class)->handle())->toBeFalse()
        ->and($sent)->toBeEmpty();

    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessJoined)->create();

    expect(app(SendPlatformDigest::class)->handle())->toBeTrue()
        ->and($sent)->toHaveCount(1)
        ->and($sent[0])->toContain(PlatformEventKind::PaymentPaused->label())
        ->and($sent[0])->toContain(route('admin.alerts'));
});

test('the board renders with an empty database', function (): void {
    Cache::flush();

    Livewire::actingAs(adminWithPhone())->test('admin.alerts.index')
        ->assertOk()
        ->assertSee(__('platform_events.screen.empty_title'));
});

/*
|--------------------------------------------------------------------------
| The certificate sensor
|--------------------------------------------------------------------------
| The origin certificate is the one that can expire unseen: Cloudflare answers
| the public name with its own, and an expired origin one is a 526 for everyone.
*/

/** Binds a reader whose certificate ends in $days and remembers what it was asked. */
function certificateEndingIn(int $days): CertificateReader
{
    $reader = new class($days) extends CertificateReader
    {
        /** @var list<array{string, string}> */
        public array $asked = [];

        public function __construct(private int $days) {}

        public function expiresAt(string $connectTo, string $serverName, int $port = 443): CarbonImmutable
        {
            $this->asked[] = [$connectTo, $serverName];

            return CarbonImmutable::now()->addDays($this->days);
        }
    };

    app()->instance(CertificateReader::class, $reader);

    return $reader;
}

function certificateLook(): SensorReadingDto
{
    return collect(app(PlatformSensors::class)->read())->firstWhere('key', 'certificate');
}

test('the certificate sensor changes colour as the end approaches', function (int $days, Status $expected): void {
    $this->freezeTime();
    config()->set('app.url', 'https://aliadito.test');
    certificateEndingIn($days);

    expect(certificateLook()->status)->toBe($expected);
})->with([
    'two months' => [60, Status::Ok],
    'just over three weeks' => [22, Status::Ok],
    'three weeks' => [21, Status::Warn],
    'a week and a day' => [8, Status::Warn],
    'a week' => [7, Status::Crit],
    'tomorrow' => [1, Status::Crit],
    'already over' => [-2, Status::Crit],
]);

test('the certificate sensor says the date and the days left, or that it already ended', function (): void {
    $this->freezeTime();
    config()->set('app.url', 'https://aliadito.test');

    certificateEndingIn(30);
    $look = certificateLook();

    expect($look->value)->toBe('30 d')
        ->and($look->detail)->toContain(now()->addDays(30)->format('d/m/Y'))->toContain('30 días');

    certificateEndingIn(-2);
    $look = certificateLook();

    expect($look->value)->toBe('vencido')->and($look->detail)->toContain('venció');
});

test('the certificate is read at the origin but asked for by the public name', function (): void {
    config()->set('app.url', 'https://aliadito.test');
    config()->set('atendia.health.certificate_origin', 'origin.internal');
    $reader = certificateEndingIn(60);

    certificateLook();

    expect($reader->asked)->toBe([['origin.internal', 'aliadito.test']]);
});

test('with no origin configured the public host itself is asked', function (): void {
    config()->set('app.url', 'https://aliadito.test');
    config()->set('atendia.health.certificate_origin', '');
    $reader = certificateEndingIn(60);

    certificateLook();

    expect($reader->asked)->toBe([['aliadito.test', 'aliadito.test']]);
});

test('without HTTPS of its own the certificate sensor has nothing to say and asks nobody', function (): void {
    config()->set('app.url', 'http://localhost');
    $reader = certificateEndingIn(60);

    $look = certificateLook();

    expect($look->status)->toBe(Status::Unknown)->and($reader->asked)->toBe([]);
});

test('an origin that does not answer is red with the reason, and the other sensors still read', function (): void {
    config()->set('app.url', 'https://aliadito.test');
    app()->instance(CertificateReader::class, new class extends CertificateReader
    {
        public function expiresAt(string $connectTo, string $serverName, int $port = 443): CarbonImmutable
        {
            throw new RuntimeException('origin.internal:443 did not answer (timed out)');
        }
    });

    $reads = collect(app(PlatformSensors::class)->read());

    expect($reads->firstWhere('key', 'certificate')->status)->toBe(Status::Crit)
        ->and($reads->firstWhere('key', 'certificate')->detail)->toContain('did not answer')
        ->and($reads->firstWhere('key', 'database')->status)->toBe(Status::Ok);
});

test('a certificate that is about to end reaches the feed after the second look, like any system sensor', function (): void {
    $this->freezeTime();
    config()->set('app.url', 'https://aliadito.test');
    certificateEndingIn(5);
    PlatformEvent::query()->delete();

    app(SweepPlatform::class)->handle();
    expect(PlatformEvent::query()->active()->where('dedupe_key', 'sensor:certificate:crit')->count())->toBe(0);

    app(SweepPlatform::class)->handle();
    expect(PlatformEvent::query()->active()->where('dedupe_key', 'sensor:certificate:crit')->count())->toBe(1);
});
