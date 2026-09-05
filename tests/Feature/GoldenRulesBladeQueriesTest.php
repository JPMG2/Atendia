<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — no queries inside Blade files
|--------------------------------------------------------------------------
| A Blade file (template OR the PHP block of a Livewire SFC) never builds a
| database query: it asks the model by domain vocabulary (options(),
| suggestionsName(), serviceNames()...). Ratchet pattern: GREEN today,
| STRICT on every new view — never extend the allowlist, fix the file.
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/**
 * Pre-existing debt, frozen with its reason. Do not add new entries.
 */
const BLADE_QUERY_ALLOWLIST = [
    'components/⚡ws-demo.blade.php', // debt: dev-only demo, dies before go-live
];

/**
 * Query entry points a Blade must never contain. Collection verbs shared
 * with the query builder (->where, ->pluck) are NOT banned: filtering an
 * in-memory list in a template is presentation, not a query.
 */
const BLADE_QUERY_PATTERNS = [
    '/::query\s*\(/',
    '/\bDB::/',
    '/::(?:where\w*|find|findOrFail|all|first|firstWhere|pluck|orderBy|latest|oldest)\s*\(/',
    '/->orderBy\s*\(/',
];

/**
 * Every Blade view keyed by its path relative to resources/views.
 *
 * @return array<string, string>
 */
function bladeQueryViews(): array
{
    $base = resource_path('views');

    return collect(File::allFiles($base))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(fn ($file): array => [
            str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($base)), '/\\')) => $file->getContents(),
        ])
        ->all();
}

test('no blade builds a query — data comes through model vocabulary', function (): void {
    $offenders = collect(bladeQueryViews())
        ->reject(fn (string $html, string $path): bool => in_array($path, BLADE_QUERY_ALLOWLIST, true))
        ->map(function (string $html): array {
            return collect(BLADE_QUERY_PATTERNS)
                ->filter(fn (string $pattern): bool => preg_match($pattern, $html) === 1)
                ->values()
                ->all();
        })
        ->filter(fn (array $matched): bool => $matched !== [])
        ->map(fn (array $matched, string $path): string => $path.' → '.implode(' · ', $matched))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
