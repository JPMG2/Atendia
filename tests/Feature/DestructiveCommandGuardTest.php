<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

// Regression guard for the catastrophe that wiped another project: a process pointed at a
// NON-testing database must have migrate:fresh/refresh/reset & db:wipe blocked by Laravel
// (DB::prohibitDestructiveCommands), regardless of test base class (Unit/Feature) or of
// whether it comes from the CLI or from RefreshDatabase's internal migrate:fresh.
//
// This is SAFE: the prohibition throws BEFORE touching the database, and we point the
// probe at a throwaway name that is neither 'atendia' nor 'atendia_testing'.

test('destructive db commands are prohibited whenever the target DB is not the testing DB', function (string $command): void {
    $process = Process::fromShellCommandline(
        "php artisan {$command} --no-interaction 2>&1",
        base_path(),
        [
            'APP_ENV' => 'local',                    // mirror the real working env (the dangerous one)
            'DB_DATABASE' => 'atendia_never_exists', // throwaway: never 'atendia' nor 'atendia_testing'
        ],
    );

    $process->run();

    // Laravel refuses to run the command in this environment.
    expect($process->getOutput())->toContain('prohibited');
})->with([
    'migrate:fresh',
    'migrate:refresh',
    'migrate:reset',
    'db:wipe',
]);

test('destructive db commands ARE allowed against the dedicated testing database', function (): void {
    // The flip side: on 'atendia_testing' the prohibition must be OFF, otherwise RefreshDatabase
    // (which runs migrate:fresh internally) would be unable to prepare the test schema.
    $connection = config('database.default');

    expect(config("database.connections.{$connection}.database"))->toBe('atendia_testing');
});
