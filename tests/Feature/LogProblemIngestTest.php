<?php

declare(strict_types=1);

use App\Actions\Admin\IngestLogProblems;
use App\Enums\LogProblemState;
use App\Models\LogProblem;
use App\Models\LogProblemHour;
use App\Models\PlatformFigure;
use App\Services\Logs\LogFingerprint;
use App\Services\Logs\LogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Log problems — what the job folds the logs into
|--------------------------------------------------------------------------
| Forty lines that differ only in a number are ONE problem; the same message
| from two places in the code are two. Only the bytes written since the last
| run are read, so running it twice never counts a line twice.
*/

beforeEach(function (): void {
    $this->logDir = sys_get_temp_dir().'/atendia-ingest-'.uniqid();
    mkdir($this->logDir);

    app()->bind(LogReader::class, fn (): LogReader => new LogReader($this->logDir));
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->logDir.'/*') ?: []);
    rmdir($this->logDir);
});

function ingest(): PlatformFigure
{
    return app(IngestLogProblems::class)->handle();
}

function appendLog(string $dir, string $content, string $file = 'laravel.log'): void
{
    file_put_contents($dir.'/'.$file, $content, FILE_APPEND);
}

test('lines that differ only in numbers and ids are one problem with their hours counted', function (): void {
    appendLog($this->logDir, <<<'LOG'
[2026-10-20 10:05:00] local.ERROR: HTTP request returned status code 502 after 1200ms
[2026-10-20 10:35:00] local.ERROR: HTTP request returned status code 503 after 90ms
[2026-10-20 11:10:00] local.ERROR: HTTP request returned status code 502 after 4000ms
LOG."\n");

    ingest();

    $problem = LogProblem::query()->sole();

    expect($problem->title)->toBe('HTTP request returned status code N after Nms')
        ->and($problem->level)->toBe('error')
        ->and($problem->first_seen_at->toDateTimeString())->toBe('2026-10-20 10:05:00')
        ->and($problem->last_seen_at->toDateTimeString())->toBe('2026-10-20 11:10:00')
        ->and($problem->last_file)->toBe('laravel.log')
        ->and($problem->state)->toBe(LogProblemState::Active)
        ->and(LogProblemHour::query()->orderBy('hour')->pluck('occurrences', 'hour')->mapWithKeys(fn ($n, $h) => [substr((string) $h, 0, 16) => $n])->all())
        ->toBe(['2026-10-20 10:00' => 2, '2026-10-20 11:00' => 1]);
});

test('the same message thrown from two places in the code is two problems', function (): void {
    $line = fn (string $where): string => '[2026-10-20 10:00:00] local.ERROR: Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /var/www/html/app/'.$where.')"}'."\n";

    appendLog($this->logDir, $line('A.php:10').$line('B.php:20'));

    ingest();

    expect(LogProblem::query()->orderBy('location')->pluck('location')->all())->toBe(['app/A.php:10', 'app/B.php:20'])
        ->and(LogProblem::query()->pluck('exception')->unique()->all())->toBe(['RuntimeException']);
});

test('what is not a problem never gets a row', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.INFO: worker started\n[2026-10-20 10:00:01] local.DEBUG: noisy\n[2026-10-20 10:00:02] local.WARNING: Disk at 91%\n");

    ingest();

    expect(LogProblem::query()->pluck('level')->all())->toBe(['warning']);
});

test('running twice never counts a line twice, and new lines are picked up', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: Boom\n");

    ingest();
    ingest();

    expect(LogProblemHour::query()->sum('occurrences'))->toBe(1);

    appendLog($this->logDir, "[2026-10-20 10:20:00] local.ERROR: Boom\n");
    $figure = ingest();

    expect(LogProblemHour::query()->sum('occurrences'))->toBe(2)
        ->and($figure->payload['entries'])->toBe(1)
        ->and(LogProblem::query()->count())->toBe(1);
});

test('a half written last line waits for the next run', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: First\n[2026-10-20 10:00:05] local.ERROR: Seco");

    ingest();

    expect(LogProblem::query()->pluck('title')->all())->toBe(['First']);

    appendLog($this->logDir, "nd\n");
    ingest();

    expect(LogProblem::query()->orderBy('title')->pluck('title')->all())->toBe(['First', 'Second']);
});

test('a file that shrank was rotated and is read from the top again', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: Old trouble that made this line long enough\n");
    ingest();

    file_put_contents($this->logDir.'/laravel.log', "[2026-10-20 11:00:00] local.ERROR: New\n");
    ingest();

    expect(LogProblem::query()->orderBy('title')->pluck('title')->all())->toBe(['New', 'Old trouble that made this line long enough']);
});

test('only the Laravel channel is read, not the other logs in the folder', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: From laravel\n", 'laravel-2026-10-20.log');
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: From a tool\n", 'build.log');

    ingest();

    expect(LogProblem::query()->pluck('title')->all())->toBe(['From laravel']);
});

test('a resolved problem that happens again comes back and says so', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: Boom\n");
    ingest();

    $problem = LogProblem::query()->sole();
    $problem->forceFill(['state' => LogProblemState::Resolved, 'state_changed_at' => '2026-10-20 10:30:00'])->save();

    // Before she resolved it: it stays resolved.
    appendLog($this->logDir, "[2026-10-20 10:10:00] local.ERROR: Boom\n");
    ingest();

    expect($problem->fresh()->state)->toBe(LogProblemState::Resolved);

    // After: it is back.
    appendLog($this->logDir, "[2026-10-20 12:00:00] local.ERROR: Boom\n");
    ingest();

    $problem->refresh();

    expect($problem->state)->toBe(LogProblemState::Active)
        ->and($problem->regressed_at->toDateTimeString())->toBe('2026-10-20 12:00:00')
        ->and($problem->tag())->toBe('back');
});

test('a muted problem keeps counting but never asks for attention', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.WARNING: csp.violation\n");
    ingest();

    LogProblem::query()->sole()->forceFill(['state' => LogProblemState::Muted, 'state_changed_at' => '2026-10-20 10:30:00'])->save();

    appendLog($this->logDir, "[2026-10-20 12:00:00] local.WARNING: csp.violation\n");
    ingest();

    expect(LogProblem::query()->sole()->state)->toBe(LogProblemState::Muted)
        ->and(LogProblemHour::query()->sum('occurrences'))->toBe(2);
});

test('history past the retention goes, and so does an old problem nobody handled', function (): void {
    $this->travelTo('2026-10-20 12:00:00');

    $old = LogProblem::factory()->create(['last_seen_at' => now()->subDays(40), 'first_seen_at' => now()->subDays(41)]);
    $handled = LogProblem::factory()->resolved()->create(['last_seen_at' => now()->subDays(40), 'first_seen_at' => now()->subDays(41)]);
    $recent = LogProblem::factory()->create(['last_seen_at' => now()->subDay()]);

    foreach ([$handled, $recent] as $problem) {
        LogProblemHour::query()->create(['log_problem_id' => $problem->id, 'hour' => now()->subDays(40)->startOfHour(), 'occurrences' => 1]);
        LogProblemHour::query()->create(['log_problem_id' => $problem->id, 'hour' => now()->subDay()->startOfHour(), 'occurrences' => 1]);
    }

    ingest();

    // A handled problem keeps its state: if it returns, it must not start over as new.
    expect(LogProblem::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(LogProblem::query()->whereKey($handled->id)->exists())->toBeTrue()
        ->and(LogProblemHour::query()->where('hour', '<', now()->subDays(30))->count())->toBe(0)
        ->and(LogProblemHour::query()->count())->toBe(2);
});

test('the job leaves its own reading so the screen can show its age', function (): void {
    appendLog($this->logDir, "[2026-10-20 10:00:00] local.ERROR: Boom\n");

    $figure = ingest();

    expect(PlatformFigure::read(IngestLogProblems::FIGURE)?->computed_at)->not->toBeNull()
        ->and($figure->payload['problems'])->toBe(1)
        ->and($figure->payload['cursors']['laravel.log']['offset'])->toBeGreaterThan(0);
});

test('the fingerprint takes out what changes every time and keeps what makes the problem', function (): void {
    $a = LogFingerprint::of('error', 'tempnam(): file created in the system\'s temporary directory (View: /var/www/html/resources/views/a.blade.php)');
    $b = LogFingerprint::of('ERROR', 'tempnam(): file created in the system\'s temporary directory (View: /var/www/html/resources/views/b.blade.php)');
    $uuid = LogFingerprint::of('error', 'Job 8f14e45f-ceea-4f6a-9d4c-0a1b2c3d4e5f failed after 3 tries');
    $other = LogFingerprint::of('error', 'Job 11111111-2222-4333-8444-555555555555 failed after 9 tries');
    $generated = LogFingerprint::of('error', 'unlink(/var/www/html/storage/debugbar/NMNANZSJRYNFNFMYNDSNXDVN.json): gone');

    expect($a->hash)->toBe($b->hash)
        ->and($uuid->hash)->toBe($other->hash)
        ->and($uuid->title)->toBe('Job {id} failed after N tries')
        ->and($generated->title)->toBe('unlink(/var/www/html/storage/debugbar/{id}.json): gone')
        // The level is part of the identity: a warning and an error are not the same trouble.
        ->and(LogFingerprint::of('warning', 'Boom')->hash)->not->toBe(LogFingerprint::of('error', 'Boom')->hash)
        ->and(LogFingerprint::of('info', 'Boom'))->toBeNull();
});
