<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — dates are ALWAYS picked with Flatpickr
|--------------------------------------------------------------------------
| Wherever a date or a date range is picked, the house control is
| <x-inputsform.datepicker> (Flatpickr in house tokens). Native date inputs
| cannot be themed and speak the OS's language; the legacy alternatives drag
| jQuery + moment. The owner's call, 2026-09-20.
|
| No allowlist on purpose: the rule was born clean. The typed d/m/Y TEXT
| fields of attribute-fields are not native date inputs, so they pass.
|
| Guide: .ai/guidelines/fechas.md
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

const NATIVE_DATE_INPUT_PATTERN = '/type\s*=\s*["\'](date|datetime-local|month|week|time)["\']/i';

const FOREIGN_DATE_LIB_PATTERN = '/daterangepicker|moment(\.min)?\.js|pikaday|cdn\.jsdelivr\.net\/npm\/flatpickr/i';

/**
 * Every file that reaches the browser, keyed by a readable path.
 *
 * @return array<string, string>
 */
function dateRuleSources(): array
{
    $sources = [];

    foreach ([resource_path('views'), resource_path('js')] as $base) {
        foreach (File::allFiles($base) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php') && $file->getExtension() !== 'js') {
                continue;
            }

            $key = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen(resource_path())), '/\\'));
            $sources[$key] = $file->getContents();
        }
    }

    return $sources;
}

test('no view or script uses a native date input', function (): void {
    $offenders = [];

    foreach (dateRuleSources() as $path => $contents) {
        if (preg_match(NATIVE_DATE_INPUT_PATTERN, $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Native date/time inputs found; use <x-inputsform.datepicker>: '.implode(', ', $offenders));
});

test('no view or script pulls another calendar library or a CDN flatpickr', function (): void {
    $offenders = [];

    foreach (dateRuleSources() as $path => $contents) {
        if (preg_match(FOREIGN_DATE_LIB_PATTERN, $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Foreign date library or CDN flatpickr found: '.implode(', ', $offenders));
});

test('the frontend dependencies carry flatpickr and none of the legacy pickers', function (): void {
    $package = json_decode((string) File::get(base_path('package.json')), true);
    $dependencies = array_keys(array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []));

    expect($dependencies)->toContain('flatpickr')
        ->and(array_intersect($dependencies, ['moment', 'daterangepicker', 'pikaday', 'jquery']))->toBe([]);
});
