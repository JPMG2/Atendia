<?php

declare(strict_types=1);

use App\Actions\Admin\IngestLogProblems;
use App\Enums\LogProblemState;
use App\Models\LogProblem;
use App\Models\LogProblemHour;
use App\Models\Menu;
use App\Models\PlatformFigure;
use App\Models\User;
use App\Services\Logs\LogFingerprint;
use App\Services\Logs\LogReader;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System logs screen (admin panel)
|--------------------------------------------------------------------------
| The logs as PROBLEMS: one row per thing that keeps happening, with the state
| somebody gave it. The lines stay in the files; the screen reads the summary a
| job keeps, shows how old that reading is, and reads the trace on demand.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    // A throwaway log directory: the tests must never read or touch the real
    // one, which the whole container writes into.
    $this->logDir = sys_get_temp_dir().'/atendia-logs-'.uniqid();
    mkdir($this->logDir);

    app()->bind(LogReader::class, fn (): LogReader => new LogReader($this->logDir));

    // The job has run: without a reading the screen says so instead of showing zeros.
    PlatformFigure::put(IngestLogProblems::FIGURE, ['cursors' => [], 'entries' => 0, 'problems' => 0]);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->logDir.'/*') ?: []);
    rmdir($this->logDir);
});

/** An admin, which is what the whole screen is gated behind. */
function logsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

/** Somebody who may read the logs but not act on them. */
function logsReader(): User
{
    $role = Role::findOrCreate('log-reader', 'web');
    $role->syncPermissions(['access-admin-panel', 'logs.view']);

    $user = User::factory()->create();
    $user->syncRoles($role);

    return $user;
}

function writeLog(string $dir, string $file, string $content): void
{
    file_put_contents($dir.'/'.$file, $content);
}

/**
 * A problem with its hourly counts: `$hours` maps "hours ago" to how many times.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<int, int>  $hours
 */
function problemWith(array $attributes, array $hours = [1 => 2]): LogProblem
{
    $problem = LogProblem::factory()->create($attributes);

    foreach ($hours as $ago => $count) {
        LogProblemHour::query()->create([
            'log_problem_id' => $problem->id,
            'hour' => now()->startOfHour()->subHours($ago),
            'occurrences' => $count,
        ]);
    }

    return $problem;
}

test('a guest is redirected to login from the logs page', function (): void {
    $this->get('/admin/logs')->assertRedirect(route('login'));
});

test('a client cannot reach the logs page', function (): void {
    $client = User::factory()->create(); // the factory assigns the client role

    $this->actingAs($client)->get('/admin/logs')->assertForbidden();
});

test('the logs page carries its translated tab title', function (): void {
    $this->actingAs(logsAdmin())->get('/admin/logs')
        ->assertOk()
        ->assertSee('<title>Logs del sistema · AtendIa</title>', false);
});

test('a screen that was never fed says so instead of showing zeros', function (): void {
    PlatformFigure::query()->delete();

    $this->actingAs(logsAdmin());

    Livewire::test('admin.logs.index')
        ->assertSee('Todavía no se leyeron los logs')
        ->assertDontSee('Nuevos hoy');
});

test('a reading that is too old is flagged as late', function (): void {
    PlatformFigure::query()->update(['computed_at' => now()->subHour()]);

    $this->actingAs(logsAdmin());

    Livewire::test('admin.logs.index')->assertSeeHtml('data-testid="logs-stale"');
});

test('the list shows what happened in the period, with how many times, and nothing else', function (): void {
    problemWith(['title' => 'Redis connection refused'], [1 => 3, 2 => 4]);
    problemWith(['title' => 'Old trouble'], [100 => 5]);

    $this->actingAs(logsAdmin());

    $component = Livewire::test('admin.logs.index')
        ->assertSee('Redis connection refused')
        ->assertDontSee('Old trouble');

    expect($component->get('range'))->toBe('day');

    $component->call('setRange', 'month')->assertSee('Old trouble');
    $rows = $component->instance()->board['rows'];

    expect($rows->firstWhere('title', 'Old trouble')->window_count)->toBe(5);
});

test('each state has its own tab and the badges count what is in the period', function (): void {
    problemWith(['title' => 'Active one']);
    problemWith(['title' => 'Active two']);
    LogProblem::factory()->resolved()->create(['title' => 'Handled']);
    $handled = LogProblem::query()->where('title', 'Handled')->sole();
    LogProblemHour::query()->create(['log_problem_id' => $handled->id, 'hour' => now()->startOfHour(), 'occurrences' => 1]);

    $this->actingAs(logsAdmin());

    $component = Livewire::test('admin.logs.index');

    expect($component->instance()->tally)->toBe(['active' => 2, 'resolved' => 1, 'muted' => 0]);

    $component->call('setState', 'resolved')->assertSee('Handled')->assertDontSee('Active one');
});

test('the level, the search and the tile focus narrow the list', function (): void {
    problemWith(['title' => 'Disk almost full', 'level' => 'warning']);
    problemWith(['title' => 'Payment gateway down', 'level' => 'error']);
    problemWith(['title' => 'Brand new thing', 'level' => 'error', 'first_seen_at' => now()->subHour()]);

    $this->actingAs(logsAdmin());

    Livewire::test('admin.logs.index')
        ->call('setLevel', 'warning')->assertSee('Disk almost full')->assertDontSee('Payment gateway down')
        ->call('setLevel', '')
        ->set('search', 'gateway')->assertSee('Payment gateway down')->assertDontSee('Disk almost full')
        ->set('search', '')
        ->set('focus', 'new')->assertSee('Brand new thing')->assertDontSee('Payment gateway down');
});

test('a problem getting worse and one that came back are the ones the second tile counts', function (): void {
    $worse = problemWith(['title' => 'Getting worse'], [2 => 5, 30 => 1]);
    $back = problemWith(['title' => 'Came back', 'regressed_at' => now()->subHour()], [1 => 1]);
    problemWith(['title' => 'Steady'], [2 => 4, 30 => 4]);

    expect(LogProblem::worseIds())->toBe([$worse->id])
        ->and(LogProblem::summary('day')['worse'])->toBe(2);

    $this->actingAs(logsAdmin());

    Livewire::test('admin.logs.index')
        ->set('focus', 'worse')
        ->assertSee('Getting worse')->assertSee('Came back')->assertDontSee('Steady');

    expect($back->fresh()->tag())->toBe('back');
});

test('the list is cut in SQL and loads more on demand', function (): void {
    foreach (range(1, 30) as $i) {
        problemWith(['title' => 'Problem number '.$i, 'last_seen_at' => now()->subMinutes($i)]);
    }

    $this->actingAs(logsAdmin());

    $component = Livewire::test('admin.logs.index');

    expect($component->instance()->board['rows'])->toHaveCount(25)
        ->and($component->instance()->board['total'])->toBe(30);

    $component->call('loadMore');

    expect($component->instance()->board['rows'])->toHaveCount(30);
});

test('resolving moves a problem out of the active tab and records who did it', function (): void {
    $problem = problemWith(['title' => 'Fix me']);
    $admin = logsAdmin();
    $this->actingAs($admin);

    Livewire::test('admin.logs.index')
        ->call('resolve', $problem->id)
        ->assertDontSee('Fix me');

    $problem->refresh();

    expect($problem->state)->toBe(LogProblemState::Resolved)
        ->and($problem->state_changed_by)->toBe($admin->id)
        ->and($problem->state_changed_at)->not->toBeNull();
});

test('muting and reopening move it through the other states', function (): void {
    $problem = problemWith(['title' => 'Noise', 'regressed_at' => now()->subDay()]);
    $this->actingAs(logsAdmin());

    $component = Livewire::test('admin.logs.index')->call('mute', $problem->id);

    expect($problem->fresh()->state)->toBe(LogProblemState::Muted)
        // Somebody looked at it: it no longer says it came back.
        ->and($problem->fresh()->regressed_at)->toBeNull();

    $component->call('reopen', $problem->id);

    expect($problem->fresh()->state)->toBe(LogProblemState::Active);
});

test('somebody who can only read the logs cannot change a problem', function (): void {
    $problem = problemWith(['title' => 'Hands off']);
    $this->actingAs(logsReader());

    Livewire::test('admin.logs.index')
        ->assertSee('Hands off')
        ->assertDontSee('Resolver')
        ->call('resolve', $problem->id)
        ->assertForbidden();

    expect($problem->fresh()->state)->toBe(LogProblemState::Active);
});

test('opening a problem shows its totals and the newest example straight from the file', function (): void {
    $problem = problemWith(['title' => 'Boom', 'last_file' => 'laravel.log'], [1 => 2, 30 => 3]);
    $fingerprint = LogFingerprint::of('error', 'Boom');
    $problem->update(['fingerprint' => $fingerprint->hash]);

    writeLog($this->logDir, 'laravel.log', <<<'LOG'
[2026-10-20 10:00:00] local.ERROR: Boom
#0 /var/www/html/vendor/laravel/framework/src/Foo.php(10): boom()
#1 {main}
LOG);

    $this->actingAs(logsAdmin());

    $component = Livewire::test('admin.logs.index')
        ->call('openProblem', $problem->id)
        ->assertSee('#0 /var/www/html/vendor/laravel/framework/src/Foo.php(10): boom()')
        ->assertSeeHtml('data-testid="log-copy"');

    expect($component->instance()->totals)->toBe(['day' => 2, 'week' => 5, 'month' => 5]);

    $component->call('closeProblem')->assertDontSee('boom()');
});

test('when the file no longer holds the example the panel says so', function (): void {
    $problem = problemWith(['title' => 'Rotated away', 'last_file' => 'laravel-2020-01-01.log']);
    $this->actingAs(logsAdmin());

    Livewire::test('admin.logs.index')
        ->call('openProblem', $problem->id)
        ->assertSee('El archivo de log ya no conserva el ejemplo');
});

test('an address with garbage in it falls back instead of breaking the screen', function (): void {
    $this->actingAs(logsAdmin());

    Livewire::withQueryParams(['periodo' => 'forever', 'estado' => 'nope', 'nivel' => 'x', 'foco' => 'y', 'problema' => 999999])
        ->test('admin.logs.index')
        ->assertSet('range', 'day')
        ->assertSet('state', 'active')
        ->assertSet('level', '')
        ->assertSet('focus', '')
        ->assertOk();
});

test('an empty tab says what will appear there', function (): void {
    $this->actingAs(logsAdmin());

    Livewire::test('admin.logs.index')
        ->assertSee('Nada por atender')
        ->call('setState', 'muted')->assertSee('Nada silenciado');
});

test('the context says what a reader would have had to ask for', function (): void {
    $context = app(LogReader::class)->context('laravel.log');

    expect($context)->toContain(PHP_VERSION)
        ->toContain(app()->version())
        ->toContain('laravel.log')
        // Never guessed: only what this process really knows.
        ->toContain(app()->environment());

    expect(app(LogReader::class)->context())->not->toContain('Archivo');
});

test('the newest example of a problem is its raw text, trace included, ready to paste', function (): void {
    writeLog($this->logDir, 'laravel.log', <<<'LOG'
[2026-10-20 09:00:00] local.ERROR: Undefined variable $slot
#0 /var/www/html/old.php(1): old()
[2026-10-20 12:30:45] local.ERROR: Undefined variable $slot
#0 /var/www/html/vendor/laravel/framework/src/Foo.php(10): boom()
#1 {main}
LOG);

    $hash = LogFingerprint::of('error', 'Undefined variable $slot')->hash;
    $raw = app(LogReader::class)->latestRaw('laravel.log', $hash);

    // Copy-paste is the whole point: nothing trimmed, nothing reformatted.
    expect($raw)->toContain('[2026-10-20 12:30:45] local.ERROR: Undefined variable $slot')
        ->toContain('#0 /var/www/html/vendor/laravel/framework/src/Foo.php(10): boom()')
        ->toContain('#1 {main}')
        ->not->toContain('old.php');

    expect(app(LogReader::class)->latestRaw('laravel.log', sha1('something else')))->toBeNull();
});

test('the suite noise files are never offered as system logs', function (): void {
    writeLog($this->logDir, 'laravel.log', "[2026-10-20 09:00:00] local.ERROR: x\n");
    writeLog($this->logDir, 'testing.log', "[2026-10-20 09:00:00] local.ERROR: x\n");
    writeLog($this->logDir, 'browser.log', "[2026-10-20 09:00:00] local.ERROR: x\n");

    // Tests error on purpose all the time; their logs are not the system's.
    expect(app(LogReader::class)->files())->toBe(['laravel.log']);
});

test('a file name that is not a known log reads nothing', function (): void {
    writeLog($this->logDir, 'laravel.log', "[2026-10-20 09:00:00] local.ERROR: x\n");

    // The name travels from the database and the browser: a path or a dot-dot must die here.
    expect(app(LogReader::class)->pathOf('../../.env'))->toBeNull()
        ->and(app(LogReader::class)->pathOf('/etc/passwd'))->toBeNull()
        ->and(app(LogReader::class)->latestRaw('../../.env', sha1('x')))->toBeNull();
});

test('the logs option hangs from the settings item of the admin menu', function (): void {
    $this->seed(MenuSeeder::class);

    $settings = Menu::query()->where('label_key', 'menu.admin_platform')->sole();

    $this->assertDatabaseHas('menus', [
        'panel' => 'admin',
        'parent_id' => $settings->id,
        'label_key' => 'menu.admin_logs',
        'route_name' => 'admin.logs',
        'icon' => 'scroll-text',
    ]);

    // The icon has to exist in the central registry or <x-icon> draws nothing.
    expect(config('icons.scroll-text'))->not->toBeNull();
});
