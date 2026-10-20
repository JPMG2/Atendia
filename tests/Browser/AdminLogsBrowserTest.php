<?php

declare(strict_types=1);

use App\Actions\Admin\IngestLogProblems;
use App\Models\LogProblem;
use App\Models\LogProblemHour;
use App\Models\PlatformFigure;
use App\Models\User;
use App\Services\Logs\LogFingerprint;
use App\Services\Logs\LogReader;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Logs del sistema — the shots a human looks at
|--------------------------------------------------------------------------
| A table of problems, not of lines: what asks for attention first, with its
| trend and its state, and the trace one click away to copy. It has to hold at
| the three widths without the page sliding sideways.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->logDir = storage_path('logs/browser-probe');

    if (! is_dir($this->logDir)) {
        mkdir($this->logDir, 0775, true);
    }

    file_put_contents($this->logDir.'/laravel.log', <<<'LOG'
[2026-10-20 14:18:02] local.ERROR: HTTP request returned status code 502
#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Client/Response.php(315): throw()
#1 /var/www/html/app/Services/Gateway.php(88): Illuminate\Http\Client\Response->throw()
#2 {main}
LOG);

    app()->bind(LogReader::class, fn (): LogReader => new LogReader($this->logDir));

    PlatformFigure::put(IngestLogProblems::FIGURE, ['cursors' => [], 'entries' => 0, 'problems' => 0]);

    $make = function (array $attributes, array $hours): LogProblem {
        $problem = LogProblem::factory()->create($attributes);

        foreach ($hours as $ago => $count) {
            LogProblemHour::query()->create(['log_problem_id' => $problem->id, 'hour' => now()->startOfHour()->subHours($ago), 'occurrences' => $count]);
        }

        return $problem;
    };

    $http = LogFingerprint::of('error', 'HTTP request returned status code 502');
    $make(['title' => 'HTTP request returned status code N', 'fingerprint' => $http->hash, 'exception' => 'RuntimeException', 'location' => 'vendor/laravel/framework/src/Illuminate/Http/Client/Response.php:315', 'last_seen_at' => now()->subMinutes(40), 'first_seen_at' => now()->subDays(4)], [1 => 2, 2 => 1, 3 => 1, 5 => 1, 6 => 1, 8 => 1, 30 => 1]);
    $make(['title' => 'Cannot assign string to property ProductDto::$service_type_id of type ?int', 'exception' => 'TypeError', 'location' => 'vendor/livewire/livewire/src/Features/SupportWireables/WireableSynth.php:51', 'last_seen_at' => now()->subMinutes(38), 'first_seen_at' => now()->subHours(5)], [1 => 1, 4 => 2, 9 => 1]);
    $make(['title' => 'csp.violation', 'level' => 'warning', 'last_seen_at' => now()->subHours(5), 'first_seen_at' => now()->subDays(5)], [6 => 1, 10 => 1, 14 => 2, 20 => 1]);
    $make(['title' => 'php_network_getaddresses: getaddrinfo for ai_project_redis-shared failed', 'exception' => 'RedisException', 'location' => 'vendor/laravel/framework/src/Illuminate/Redis/Connectors/PhpRedisConnector.php:164', 'last_seen_at' => now()->subHours(20), 'first_seen_at' => now()->subDays(2), 'regressed_at' => now()->subHours(20)], [20 => 3]);
    $make(['title' => 'Too few arguments to function CarbonImmutable::rawCreateFromFormat()', 'exception' => 'ArgumentCountError', 'state' => 'resolved', 'state_changed_at' => now()->subDay(), 'last_seen_at' => now()->subDays(5)], [3 => 1]);

    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

afterEach(function (): void {
    @unlink($this->logDir.'/laravel.log');
    @rmdir($this->logDir);
});

test('the logs screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.logs'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Logs del sistema')
        ->assertSee('Nuevos hoy')
        ->assertSee('Se repiten más')
        ->assertSee('HTTP request returned status code N')
        ->assertSee('Nuevo')
        ->assertSee('Regresó')
        ->screenshot(filename: 'admin-logs-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    // The table must fit its card: a column cut off by the wrapper reads fine to the page check.
    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 1200, false],
    'phone dark' => ['phone', 390, 1200, true],
]);

test('resolving takes the problem out of the active tab and reopening brings it back', function (): void {
    $page = visit(route('admin.logs'))->resize(1280, 900);

    $page->assertSee('csp.violation');

    $page->script("[...document.querySelectorAll('.lg-table tbody tr')].find((row) => row.textContent.includes('csp.violation')).querySelector('[data-testid=log-resolve]').click()");
    $page->wait(1)->assertDontSee('csp.violation')->assertNoJavaScriptErrors();

    $page->script("[...document.querySelectorAll('[role=tab]')].find((tab) => tab.textContent.includes('Resueltos')).click()");
    $page->wait(1)->assertSee('csp.violation')->assertSee('Reabrir')->screenshot(filename: 'admin-logs-resolved');

    expect(LogProblem::query()->where('title', 'csp.violation')->sole()->state->value)->toBe('resolved');
});

test('a tile is a door: it leads to the list it counts', function (): void {
    $page = visit(route('admin.logs'))->resize(1280, 900);

    $page->script("document.querySelector('[data-testid=tile-new]').click()");
    $page->wait(1)->assertSee('Cannot assign string')->assertDontSee('csp.violation')->assertNoJavaScriptErrors();
});

test('opening a problem shows its trace, ready to copy, in a side panel', function (): void {
    $page = visit(route('admin.logs'))->resize(1280, 1000);

    $page->script("[...document.querySelectorAll('.lg-table tbody tr')].find((row) => row.textContent.includes('HTTP request returned')).click()");
    $page->wait(1)
        ->assertSee('Ejemplo más reciente')
        ->assertSee('Gateway.php(88)')
        ->assertSee('Copiar para pedir ayuda')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-logs-detail');
});
