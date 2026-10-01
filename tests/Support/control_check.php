<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Single-file live-control check (layer C)
|--------------------------------------------------------------------------
| Entry point for the PostToolUse hook. It reuses the very scanner the
| guardian test runs on, so the two layers can never drift apart.
|
| Usage: php tests/Support/control_check.php <path relative to the project>
*/

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/ControlScanner.php';

use Tests\Support\ControlScanner;

$relative = $argv[1] ?? '';
$absolute = __DIR__.'/../../'.$relative;

if ($relative === '' || ! is_file($absolute)) {
    exit(0);
}

$silent = ControlScanner::silentControlsIn($relative, (string) file_get_contents($absolute));

if ($silent === []) {
    exit(0);
}

fwrite(STDERR, "Action controls that run nothing in {$relative}:\n");

foreach ($silent as $control) {
    fwrite(STDERR, '  - line '.$control['line'].': '.$control['tag']."\n");
}

fwrite(STDERR, "\nWire it (wire:click, href, x-on:click, a data- hook) or stop drawing it.\nRule: .ai/guidelines/controles-vivos.md\n");

exit(1);
