<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Single-file query check (layer C)
|--------------------------------------------------------------------------
| Entry point for the PostToolUse hook. It reuses the very scanner the
| guardian test runs on, so the two layers can never drift apart.
|
| Usage: php tests/Support/query_check.php <path relative to the project>
*/

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/QueryScanner.php';

use Tests\Support\QueryScanner;

$relative = $argv[1] ?? '';

// A model edit can turn a form red without touching it (a method or a trait
// gone), so `--all` judges every file the rule reaches, as the guardian does.
if ($relative === '--all') {
    $broken = collect(QueryScanner::files())
        ->map(fn (string $source, string $path): array => QueryScanner::offensesIn($path, $source))
        ->filter(fn (array $offenses): bool => $offenses !== []);
} else {
    $absolute = str_starts_with($relative, '/') ? $relative : __DIR__.'/../../'.$relative;

    if ($relative === '' || ! is_file($absolute)) {
        exit(0);
    }

    $broken = collect([$relative => QueryScanner::offensesIn($relative, (string) file_get_contents($absolute))])
        ->filter(fn (array $offenses): bool => $offenses !== []);
}

if ($broken->isEmpty()) {
    exit(0);
}

foreach ($broken as $path => $offenses) {
    fwrite(STDERR, "Golden rule broken in {$path}: it builds a query ({$offenses[0]}).\n");
}

fwrite(STDERR, "Ask the MODEL by a domain name (options(), forEditing(), editable()...) and call that.\n");
fwrite(STDERR, "Rule: .ai/guidelines/queries-en-el-modelo.md\n");

exit(1);
