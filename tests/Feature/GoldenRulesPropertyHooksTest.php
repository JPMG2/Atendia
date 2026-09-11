<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — plain PHP classes expose getters as property hooks
|--------------------------------------------------------------------------
| In app/Classes and app/Dto a zero-argument public getter is written as a
| PHP 8.4 property hook (public Type $x { get => ... }), never as a method:
| callers read $piece->links, not $piece->links(). Conversions (to… and
| from…) and magic methods stay methods; static factories and anything taking
| arguments are untouched. Eloquent models and Livewire components/Forms are
| OUT of scope: their magic and hydration do not mix with hooks.
|
| No allowlist: it reached zero the day the rule was born (2026-09-11).
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/**
 * A public, non-static, zero-parameter method whose name is not a to… or
 * from… conversion nor a magic method. Mirrored in
 * check-php-getter-golden-rules.sh: touch one, touch the other.
 */
const PROPERTY_HOOK_PATTERN = '/(?<!static )public\s+function\s+(?!to[A-Z]|from[A-Z]|__)([a-zA-Z_]\w*)\s*\(\s*\)/';

/** @return array<string, string> Project-relative path => file contents. */
function plainPhpClassSources(): array
{
    return collect([app_path('Classes'), app_path('Dto')])
        ->flatMap(fn (string $base): array => File::allFiles($base))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.php'))
        ->mapWithKeys(fn (SplFileInfo $file): array => [
            str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen(base_path())), '/\\')) => $file->getContents(),
        ])
        ->all();
}

test('plain PHP classes expose zero-argument getters as property hooks, not methods', function (): void {
    $offenders = collect(plainPhpClassSources())
        ->map(function (string $code): array {
            preg_match_all(PROPERTY_HOOK_PATTERN, $code, $matches);

            return $matches[1];
        })
        ->filter(fn (array $methods): bool => $methods !== [])
        ->map(fn (array $methods, string $path): string => $path.' → '.implode(' · ', $methods).'()')
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
