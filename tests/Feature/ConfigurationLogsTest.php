<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use App\Services\Logs\LogReader;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System logs screen (admin panel)
|--------------------------------------------------------------------------
| The latest entries of the system logs, newest first, each one carrying its
| raw text VERBATIM so it can be copied straight into a help conversation.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // A throwaway log directory: the tests must never read or touch the real
    // one, which the whole container writes into.
    $this->logDir = sys_get_temp_dir().'/atendia-logs-'.uniqid();
    mkdir($this->logDir);

    app()->bind(LogReader::class, fn (): LogReader => new LogReader($this->logDir));
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

function writeLog(string $dir, string $file, string $content): void
{
    file_put_contents($dir.'/'.$file, $content);
}

/** Two entries, the second with the multiline trace a real error drags. */
function sampleLog(): string
{
    return <<<'LOG'
[2026-09-02 10:00:00] local.INFO: The queue worker started.
[2026-09-02 12:30:45] local.ERROR: Undefined variable $slot {"exception":"[object] (ErrorException)
#0 /var/www/html/vendor/laravel/framework/src/Foo.php(10): boom()
#1 {main}
"}
LOG;
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
        ->assertSee('<title>Logs del sistema</title>', false);
});

test('an admin sees the latest entries, newest first', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());

    $this->actingAs(logsAdmin());

    $component = Livewire::test('configuration.logs');

    $entries = $component->get('entries');

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['level'])->toBe('error')
        ->and($entries[0]['message'])->toContain('Undefined variable $slot')
        ->and($entries[1]['level'])->toBe('info');
});

test('an entry carries its raw text verbatim, trace included, ready to paste', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());

    $entries = app(LogReader::class)->entries('laravel.log');

    // Copy-paste is the whole point: nothing trimmed, nothing reformatted.
    expect($entries->first()->raw)
        ->toContain('[2026-09-02 12:30:45] local.ERROR: Undefined variable $slot')
        ->toContain('#0 /var/www/html/vendor/laravel/framework/src/Foo.php(10): boom()')
        ->toContain('#1 {main}');
});

test('the copy control feeds the clipboard from the raw block', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());

    $this->actingAs(logsAdmin());

    $html = Livewire::test('configuration.logs')->html();

    expect($html)->toContain('navigator.clipboard.writeText($refs.raw.textContent)')
        ->toContain('x-ref="raw"');
});

test('only the tail of a huge log is read, and only whole entries survive', function (): void {
    // One entry per line, far beyond the tail window: the reader must come
    // back fast, with the LAST entries, dropping the line the cut split.
    $lines = [];

    foreach (range(1, 30000) as $i) {
        $lines[] = sprintf('[2026-09-02 09:%02d:%02d] local.INFO: Entry number %d.', intdiv($i, 60) % 60, $i % 60, $i);
    }

    writeLog($this->logDir, 'laravel.log', implode("\n", $lines));

    $entries = app(LogReader::class)->entries('laravel.log');

    expect($entries)->toHaveCount(100)
        ->and($entries->first()->message)->toBe('Entry number 30000.')
        ->and($entries->every(fn ($entry) => str_starts_with($entry->message, 'Entry number')))->toBeTrue();
});

test('the suite noise files are never offered as system logs', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());
    writeLog($this->logDir, 'testing.log', sampleLog());
    writeLog($this->logDir, 'browser.log', sampleLog());

    // Tests error on purpose all the time; their logs are not the system's.
    expect(app(LogReader::class)->files())->toBe(['laravel.log'])
        ->and(app(LogReader::class)->entries('testing.log'))->toHaveCount(0)
        ->and(app(LogReader::class)->entries('browser.log'))->toHaveCount(0);
});

test('a file name that is not a known log reads nothing', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());

    // The name travels from the browser: a path or a dot-dot must die here.
    expect(app(LogReader::class)->entries('../../.env'))->toHaveCount(0)
        ->and(app(LogReader::class)->entries('/etc/passwd'))->toHaveCount(0);
});

test('the screen refuses to switch to a file outside the known list', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());

    $this->actingAs(logsAdmin());

    Livewire::test('configuration.logs')
        ->call('selectFile', '../../.env')
        ->assertSet('file', 'laravel.log');
});

test('with several log files the screen offers them and switches', function (): void {
    writeLog($this->logDir, 'laravel.log', sampleLog());
    writeLog($this->logDir, 'worker.log', "[2026-09-02 11:00:00] local.WARNING: The worker said something.\n");

    $this->actingAs(logsAdmin());

    $component = Livewire::test('configuration.logs')
        ->call('selectFile', 'worker.log')
        ->assertSet('file', 'worker.log');

    expect($component->get('entries'))->toHaveCount(1)
        ->and($component->get('entries')[0]['level'])->toBe('warning');
});

test('an empty log says so instead of showing a blank page', function (): void {
    writeLog($this->logDir, 'laravel.log', '');

    $this->actingAs(logsAdmin());

    Livewire::test('configuration.logs')->assertSee(__('logs.empty'));
});

test('the logs option hangs from the settings item of the admin menu', function (): void {
    $this->seed(MenuSeeder::class);

    $settings = Menu::query()->where('label_key', 'menu.admin_settings')->sole();

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
