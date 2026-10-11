<?php

declare(strict_types=1);

use App\Actions\Admin\RankBusinessActivity;
use App\Mail\BusinessActivityStale;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Models\PlatformFigure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Admin home — the businesses with the most movement, worked out by a job
|--------------------------------------------------------------------------
| The card reads a stored figure, so what has to be true is: the job ranks
| the real thing, a demo never ranks, and the screen shows the age of what it
| reads — "never ran" and "ran long ago" are not the same sentence as zero.
*/

beforeEach(function (): void {
    app()->setLocale('es');
});

function customerMessages(Business $business, int $count, ?DateTimeInterface $at = null): void
{
    ConversationMessage::factory()->count($count)->create([
        'business_id' => $business->id,
        'created_at' => $at ?? now()->subDay(),
    ]);
}

function aiCalls(Business $business, int $count): void
{
    foreach (range(1, $count) as $unused) {
        AiUsage::query()->create([
            'business_id' => $business->id,
            'kind' => 'AssistantAgent',
            'model' => 'gpt-test',
            'input_tokens' => 10,
            'cached_tokens' => 0,
            'output_tokens' => 5,
        ]);
    }
}

test('it ranks real businesses by WhatsApp messages and by AI calls, most first', function (): void {
    $big = Business::factory()->create(['name' => 'Grande']);
    $small = Business::factory()->create(['name' => 'Chico']);
    customerMessages($big, 5);
    customerMessages($small, 2);
    aiCalls($small, 4);
    aiCalls($big, 1);

    $figure = app(RankBusinessActivity::class)->handle();

    expect(collect($figure->payload['whatsapp'])->pluck('name')->all())->toBe(['Grande', 'Chico'])
        ->and(collect($figure->payload['whatsapp'])->pluck('total')->all())->toBe([5, 2])
        ->and(collect($figure->payload['ai'])->pluck('name')->all())->toBe(['Chico', 'Grande']);
});

test('a demo business of the landing never ranks, and neither does what is out of the window', function (): void {
    $demo = Business::factory()->create(['name' => 'Demo', 'is_demo' => true]);
    $old = Business::factory()->create(['name' => 'Antiguo']);
    customerMessages($demo, 9);
    customerMessages($old, 9, now()->subDays(RankBusinessActivity::WINDOW_DAYS + 5));

    $figure = app(RankBusinessActivity::class)->handle();

    expect($figure->payload['whatsapp'])->toBe([]);
});

test('running it twice leaves one figure, corrected', function (): void {
    app(RankBusinessActivity::class)->handle();
    app(RankBusinessActivity::class)->handle();

    expect(PlatformFigure::query()->where('key', PlatformFigure::ACTIVITY)->count())->toBe(1);
});

test('the console command stores the figure the home reads', function (): void {
    customerMessages(Business::factory()->create(['name' => 'Grande']), 3);

    $this->artisan('atendia:business-activity')->assertSuccessful();

    expect(PlatformFigure::read(PlatformFigure::ACTIVITY)->payload['whatsapp'][0]['name'])->toBe('Grande');
});

function activityAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create(['email_verified_at' => now(), 'two_factor_whatsapp_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

test('the home says the ranking never ran instead of drawing an empty one', function (): void {
    activityAdmin();

    Livewire::test('admin.home.index')
        ->assertSee(__('admin.home.activity.never'))
        ->assertDontSeeHtml('data-testid="activity-stale"');
});

test('the home shows the ranking with the moment it was computed and links each business', function (): void {
    activityAdmin();
    $business = Business::factory()->create(['name' => 'Grande']);
    customerMessages($business, 3);
    app(RankBusinessActivity::class)->handle();

    Livewire::test('admin.home.index')
        ->assertSee('Grande')
        ->assertSee(__('admin.home.activity.computed', ['date' => now()->format('d/m/Y H:i')]))
        ->assertSeeHtml(e(route('admin.businesses', ['negocio' => $business->id])))
        ->assertDontSeeHtml('data-testid="activity-stale"');
});

test('a ranking older than the job allows is shown as late, not as current', function (): void {
    activityAdmin();
    app(RankBusinessActivity::class)->handle();
    PlatformFigure::query()->update(['computed_at' => now()->subMinutes(RankBusinessActivity::STALE_AFTER_MINUTES + 10)]);

    Livewire::test('admin.home.index')->assertSeeHtml('data-testid="activity-stale"');
});

test('the receipts region refreshes by itself, as one island, without a page reload', function (): void {
    activityAdmin();

    Livewire::test('admin.home.index')
        ->assertSeeHtml('wire:poll.30s.visible')
        ->assertSee(__('admin.home.live', ['seconds' => 30]));
});

/** Three businesses whose places changed against the 30 days before. */
function movedBusinesses(): array
{
    $before = now()->subDays(40);

    $climbs = Business::factory()->create(['name' => 'Sube']);
    $falls = Business::factory()->create(['name' => 'Baja']);
    $arrives = Business::factory()->create(['name' => 'Llega']);

    // Previous period: Baja first, Sube second. Now: Sube first, Baja second.
    customerMessages($falls, 9, $before);
    customerMessages($climbs, 5, $before);
    customerMessages($climbs, 8);
    customerMessages($falls, 3);
    customerMessages($arrives, 1);

    return compact('climbs', 'falls', 'arrives');
}

test('each ranked business carries the place it held before, and null when it was not ranked', function (): void {
    movedBusinesses();

    $rows = collect(app(RankBusinessActivity::class)->handle()->payload['whatsapp'])->keyBy('name');

    expect($rows['Sube'])->toMatchArray(['rank' => 1, 'previous_rank' => 2])
        ->and($rows['Baja'])->toMatchArray(['rank' => 2, 'previous_rank' => 1])
        ->and($rows['Llega']['rank'])->toBe(3)
        ->and($rows['Llega']['previous_rank'])->toBeNull();
});

test('the home shows who went up, who went down and who is new, and says so in words', function (): void {
    activityAdmin();
    movedBusinesses();
    app(RankBusinessActivity::class)->handle();

    Livewire::test('admin.home.index')
        ->assertSeeHtml('data-testid="activity-trend-up"')
        ->assertSeeHtml('data-testid="activity-trend-down"')
        ->assertSeeHtml('data-testid="activity-trend-new"')
        ->assertSeeHtml(e(trans_choice('admin.home.activity.trend.up', 1, ['count' => 1])))
        ->assertSeeHtml(e(__('admin.home.activity.trend.new')));
});

test('a ranking stored before the comparison existed shows no arrows instead of calling everyone new', function (): void {
    activityAdmin();
    PlatformFigure::put(PlatformFigure::ACTIVITY, [
        'days' => 30,
        'whatsapp' => [['business_id' => Business::factory()->create(['name' => 'Vieja'])->id, 'name' => 'Vieja', 'total' => 3]],
        'ai' => [],
    ]);

    Livewire::test('admin.home.index')
        ->assertSee('Vieja')
        ->assertDontSeeHtml('data-testid="activity-trend-new"');
});

test('the pulse is green on time, amber when late and red when overdue', function (int $minutes, string $beat): void {
    activityAdmin();
    app(RankBusinessActivity::class)->handle();
    PlatformFigure::query()->update(['computed_at' => now()->subMinutes($minutes)]);

    Livewire::test('admin.home.index')->assertSeeHtml('data-beat="'.$beat.'"');
})->with([
    'just ran' => [10, 'fresh'],
    'past the slack' => [100, 'late'],
    'overdue' => [RankBusinessActivity::STALE_AFTER_MINUTES + 5, 'stale'],
]);

describe('the watcher', function (): void {
    beforeEach(function (): void {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'duena@atendia.test']);
        config()->set('atendia.admin_email', $admin->email);
    });

    test('it warns the admin once per outage, not once per check', function (): void {
        app(RankBusinessActivity::class)->handle();
        PlatformFigure::query()->update(['computed_at' => now()->subMinutes(RankBusinessActivity::STALE_AFTER_MINUTES + 30)]);

        $this->artisan('atendia:business-activity-watch')->assertSuccessful();
        $this->artisan('atendia:business-activity-watch')->assertSuccessful();

        Mail::assertQueued(BusinessActivityStale::class, 1);
        Mail::assertQueued(BusinessActivityStale::class, fn (BusinessActivityStale $mail): bool => $mail->hasTo('duena@atendia.test'));
    });

    test('a recovery that breaks again earns a new warning', function (): void {
        app(RankBusinessActivity::class)->handle();
        PlatformFigure::query()->update(['computed_at' => now()->subMinutes(300)]);
        $this->artisan('atendia:business-activity-watch')->assertSuccessful();

        PlatformFigure::query()->update(['computed_at' => now()->subMinutes(200)]);
        $this->artisan('atendia:business-activity-watch')->assertSuccessful();

        Mail::assertQueued(BusinessActivityStale::class, 2);
    });

    test('it stays quiet while the ranking is current and before it ever ran', function (): void {
        $this->artisan('atendia:business-activity-watch')->assertSuccessful();

        app(RankBusinessActivity::class)->handle();
        $this->artisan('atendia:business-activity-watch')->assertSuccessful();

        Mail::assertNothingQueued();
    });
});
