<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule — schema changes redesign the CREATE migration
|--------------------------------------------------------------------------
| User convention (2026-09-02): while the product has not launched, a new
| column never ships as an `add_*_to_*` migration — the table's CREATE
| migration is redesigned instead, `atendia` is synced with a surgical
| ALTER, and `atendia_testing` rebuilds itself via RefreshDatabase.
| Migrations older than the convention stay as history.
*/

const CONVENTION_CUTOFF = '2026_09_02';

test('no add/drop/rename migration is created after the consolidation rule', function (): void {
    $offenders = collect(File::files(database_path('migrations')))
        ->map(fn ($file): string => $file->getFilename())
        ->filter(fn (string $name): bool => preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $name) === 1
            && substr($name, 0, 10) > CONVENTION_CUTOFF)
        ->filter(fn (string $name): bool => preg_match('/_(add|drop|rename)_\w+_(to|from|on|in)_\w+_table\.php$/', $name) === 1)
        ->values();

    expect($offenders->all())->toBe([]);
});
