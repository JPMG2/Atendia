<?php

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — Blade markup (structure/markup rules)
|--------------------------------------------------------------------------
| Deterministic guarantee for the form/markup golden rules. Ratchet pattern:
| GREEN today, STRICT on every NEW view. Pre-existing files are allowlisted
| with a reason — never extend these lists, fix the file instead.
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/**
 * Sanctioned homes for raw <input>/<select>/<textarea>: the primitive UI
 * library and Breeze legacy primitives. New forms MUST use <x-ui.*> wrappers
 * (that is what guarantees the single focus ring, theming and error wiring).
 */
const RAW_CONTROL_ALLOWLIST = [
    'components/ui/input.blade.php',
    'components/ui/select.blade.php',
    'components/ui/textarea.blade.php',
    'components/ui/switch.blade.php',
    'components/ui/checkbox.blade.php',
    'components/text-input.blade.php',  // Breeze legacy primitive
    'auth/reset-password.blade.php',    // hidden token input (Breeze)
];

/**
 * Hardcoded hex is allowed ONLY here. The checkbox tick is a legit exception;
 * the site/* entries are PRE-EXISTING DEBT (inline #fff / #7CFFC4) to migrate
 * to semantic tokens. Do not add new entries.
 */
const HEX_ALLOWLIST = [
    'components/ui/checkbox.blade.php',      // white tick of the native checkbox
    'components/site/phone-mock.blade.php',  // debt: inline #fff / #7CFFC4
    'components/site/closing-cta.blade.php', // debt: inline #fff
    'components/site/pricing.blade.php',     // debt: inline #fff
];

/**
 * Every Blade view keyed by its path relative to resources/views.
 *
 * @return array<string, string>
 */
function bladeViews(): array
{
    $base = resource_path('views');

    return collect(File::allFiles($base))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(fn ($file): array => [
            str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($base)), '/\\')) => $file->getContents(),
        ])
        ->all();
}

test('no view uses a raw input, select or textarea — forms must use <x-ui.*>', function (): void {
    $offenders = collect(bladeViews())
        ->reject(fn (string $html, string $path): bool => in_array($path, RAW_CONTROL_ALLOWLIST, true))
        ->filter(fn (string $html): bool => preg_match('/<(input|select|textarea)\b/i', $html) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view hardcodes a hex color — use semantic tokens from app.css', function (): void {
    $offenders = collect(bladeViews())
        ->reject(fn (string $html, string $path): bool => in_array($path, HEX_ALLOWLIST, true))
        ->filter(fn (string $html): bool => preg_match('/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $html) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view wires Lucide via data-lucide or createIcons — icons go through <x-icon>', function (): void {
    $offenders = collect(bladeViews())
        ->filter(fn (string $html): bool => preg_match('/data-lucide|lucide\.createIcons/i', $html) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});
