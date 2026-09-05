<?php

declare(strict_types=1);

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
    'components/inputsform/input.blade.php',  // form-input primitive library (inputsform)
    'components/inputsform/select.blade.php', // idem: the select sibling of that same library
    'components/inputsform/combobox.blade.php', // idem: autocomplete select (search input + hidden value)
    'components/inputsform/file.blade.php', // idem: the drop zone over a native file input
    'components/inputsform/phone.blade.php', // idem: dial select + national number over a hidden composite
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
    // Mail views are the one legit DIRECTORY exception: their CSS is inlined
    // on send and mail clients never load app.css, so no token exists there.
    $offenders = collect(bladeViews())
        ->reject(fn (string $html, string $path): bool => in_array($path, HEX_ALLOWLIST, true))
        ->reject(fn (string $html, string $path): bool => str_starts_with($path, 'emails/') || str_starts_with($path, 'components/email/'))
        ->filter(fn (string $html): bool => preg_match('/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $html) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view hardcodes the page title via #[Title] — titles come from translations', function (): void {
    // A PHP attribute only takes constants, so #[Title('...')] is always
    // hardcoded copy. The sanctioned paths: render() with
    // $this->view()->title(__('...')), or the layout's translated default.
    $offenders = collect(bladeViews())
        ->filter(fn (string $html): bool => preg_match('/#\[Title\(/', $html) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view wires Lucide via data-lucide or createIcons — icons go through <x-icon>', function (): void {
    $offenders = collect(bladeViews())
        ->filter(fn (string $html): bool => preg_match('/data-lucide|lucide\.createIcons/i', $html) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view uses a .col-N grid class that is not defined in app.css — inputs must not overlap', function (): void {
    // Which grid columns the design system actually defines (e.g. `.col-4`).
    // A form using an undefined `col-N` collapses that field to one column and
    // the inputs overlap — a layout bug the other guards cannot see.
    preg_match_all('/\.col-(\d+)\b/', File::get(resource_path('css/app.css')), $matches);
    $defined = array_map('intval', $matches[1]);

    $offenders = collect(bladeViews())
        ->map(function (string $html) use ($defined): array {
            preg_match_all('/\bcol-(\d+)\b/', $html, $used);

            return collect($used[1])
                ->map(fn (string $n): int => (int) $n)
                ->unique()
                ->reject(fn (int $n): bool => in_array($n, $defined, true))
                ->values()
                ->all();
        })
        ->filter(fn (array $missing): bool => $missing !== [])
        ->map(fn (array $missing, string $path): string => $path.' → col-'.implode(', col-', $missing))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
