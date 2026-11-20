<?php

declare(strict_types=1);

use App\Classes\Main\Releases;
use App\Console\Commands\AnnounceRelease;
use App\Enums\PanelNotificationType;
use App\Jobs\SendBellPush;
use App\Mail\ReleaseNews;
use App\Models\Business;
use App\Models\PanelNotification;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A new version reaches the customers: the bell, the menu and the phone
|--------------------------------------------------------------------------
| Only a version that adds something is news. The first one is where every
| customer started, and a patch only fixes what they already had.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Cache::forget(AnnounceRelease::ANNOUNCED_KEY);
    // Every test starts where every customer did: the first version alone.
    config(['releases' => [['version' => '1.0.0', 'date' => '2026-11-19', 'kind' => 'major']]]);
});

/** Puts a version above 1.0.0, as a real release would. */
function shipVersion(string $version, string $kind): void
{
    config(['releases' => [
        ['version' => $version, 'date' => '2026-12-01', 'kind' => $kind],
        ['version' => '1.0.0', 'date' => '2026-11-19', 'kind' => 'major'],
    ]]);
}

test('the first version and a patch are not news, a minor one is', function (): void {
    expect((new Releases)->news)->toBeNull();

    shipVersion('1.0.1', 'patch');
    expect((new Releases)->news)->toBeNull();

    shipVersion('1.1.0', 'minor');
    expect((new Releases)->news['version'])->toBe('1.1.0');
});

test('a version with news lands once in every business bell and leads to «Novedades»', function (): void {
    [$first, $second] = Business::factory()->count(2)->create();
    shipVersion('1.1.0', 'minor');

    $this->artisan('atendia:announce-release')->assertSuccessful();
    // A lost cache walks again without stacking a second row.
    Cache::forget(AnnounceRelease::ANNOUNCED_KEY);
    $this->artisan('atendia:announce-release')->assertSuccessful();

    $notices = PanelNotification::query()->where('type', PanelNotificationType::ReleaseAvailable)->get();

    expect($notices)->toHaveCount(2)
        ->and($notices->pluck('business_id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($notices->first()->url)->toBe(route('releases'))
        ->and($notices->first()->payload)->toBe(['version' => '1.1.0']);
});

test('a lost cache walks again without buzzing anyone twice', function (): void {
    Business::factory()->count(2)->create();
    shipVersion('1.1.0', 'minor');
    $this->artisan('atendia:announce-release');

    Queue::fake();
    Cache::forget(AnnounceRelease::ANNOUNCED_KEY);
    $this->artisan('atendia:announce-release');

    Queue::assertNotPushed(SendBellPush::class);
});

test('a version older than the bell keeps its rows is never announced', function (): void {
    Business::factory()->create();
    config(['releases' => [
        ['version' => '1.1.0', 'date' => now()->subDays((int) config('atendia.bell.keep_days'))->toDateString(), 'kind' => 'minor'],
        ['version' => '1.0.0', 'date' => '2026-01-01', 'kind' => 'major'],
    ]]);

    $this->artisan('atendia:announce-release');

    expect(PanelNotification::query()->count())->toBe(0);
});

test('a suspended business is not told what is new', function (): void {
    $open = Business::factory()->create();
    Business::factory()->create(['suspended_at' => now()]);
    shipVersion('1.1.0', 'minor');

    $this->artisan('atendia:announce-release');

    expect(PanelNotification::query()->pluck('business_id')->all())->toBe([$open->id]);
});

test('each business gets the news by mail once, with what the version brings', function (): void {
    Mail::fake();
    Business::factory()->create(['billing_email' => 'dueña@negocio.test']);
    Business::factory()->create(['billing_email' => '']);
    shipVersion('1.1.0', 'minor');

    $this->artisan('atendia:announce-release');
    Cache::forget(AnnounceRelease::ANNOUNCED_KEY);
    $this->artisan('atendia:announce-release');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(ReleaseNews::class, fn (ReleaseNews $mail): bool => $mail->hasTo('dueña@negocio.test')
        && $mail->release['version'] === '1.1.0');
});

test('the mail stays off when she turns it off in the admin', function (): void {
    Mail::fake();
    config(['atendia.releases.mail_owners' => 0]);
    Business::factory()->create(['billing_email' => 'dueña@negocio.test']);
    shipVersion('1.1.0', 'minor');

    $this->artisan('atendia:announce-release');

    Mail::assertNothingQueued();
    expect(PanelNotification::query()->count())->toBe(1);
});

test('the mail lists every line of the version and leads to «Novedades»', function (): void {
    $business = Business::factory()->create();
    $mail = new ReleaseNews($business, ['version' => '1.1.0', 'title' => 'Algo nuevo', 'items' => ['Primera línea', 'Segunda línea']]);

    $mail->assertSeeInHtml('Algo nuevo')
        ->assertSeeInHtml('Primera línea')
        ->assertSeeInHtml('Segunda línea')
        ->assertSeeInHtml(route('releases'))
        ->assertSeeInText('Segunda línea');
});

test('without news the bell gets nothing', function (): void {
    Business::factory()->create();

    $this->artisan('atendia:announce-release')->assertSuccessful();

    expect(PanelNotification::query()->count())->toBe(0);
});

test('the bell row says which version came out', function (): void {
    $user = payingUser();
    shipVersion('1.1.0', 'minor');
    $this->artisan('atendia:announce-release');

    $rendered = Livewire\Livewire::actingAs($user)->test('client.bell')->call('open')->html();

    expect($rendered)->toContain(__('bell.lines.release_available', ['version' => '1.1.0']));
});

test('the menu marks «Novedades» as new only when the version brings news', function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MenuSeeder::class, PlanSeeder::class]);
    $user = payingUser();
    $user->assignRole('client');

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('data-testid="sidebar-version-new"', false);

    shipVersion('1.1.0', 'minor');

    $this->get('/dashboard')->assertSee('data-testid="sidebar-version-new"', false);
    // The page is what turns the mark off, for the same version the menu compares with.
    $this->get('/novedades')->assertSee(Releases::SEEN_KEY, false)->assertSee('1.1.0');
});

test('the phone reads the platform version and where its news live', function (): void {
    $this->getJson('/api/v1/brand')
        ->assertOk()
        ->assertJsonPath('data.release.version', (new Releases)->current)
        ->assertJsonPath('data.release.label', 'v'.(new Releases)->current.' · Novedades')
        ->assertJsonPath('data.release.url', route('releases'));
});
