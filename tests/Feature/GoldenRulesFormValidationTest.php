<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — validation lives in a Form, never in a component
|--------------------------------------------------------------------------
| A Livewire component (class OR the PHP block of an SFC) never validates on
| its own: no inline rules()/validationAttributes(), no $this->validate().
| The state, the rules and the save live in a Form extending BaseForm
| (validateServiceData() + AttributeValidator); the component only delegates.
| Born 2026-09-09: six profile cards shipped with inline validation while
| every catalog editor, the wizard and the company screen used Forms.
|
| No allowlist: it reached zero the day the rule was born. Fix the file.
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/**
 * Inline-validation entry points a component must not contain. The Form's
 * own door, validateServiceData(), does not match `$this->validate(`.
 */
const FORM_VALIDATION_PATTERNS = [
    '/function\s+rules\s*\(/',
    '/function\s+validationAttributes\s*\(/',
    '/\$this->validate(?:Only)?\s*\(/',
];

/**
 * Every file where component code lives, keyed by its project-relative path:
 * Blade views (SFCs carry their class inline), Livewire classes and traits.
 *
 * @return array<string, string>
 */
function formValidationSources(): array
{
    $bases = [resource_path('views'), app_path('Livewire'), app_path('Traits')];

    return collect($bases)
        ->flatMap(fn (string $base): array => File::allFiles($base))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.php'))
        ->mapWithKeys(fn (SplFileInfo $file): array => [
            str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen(base_path())), '/\\')) => $file->getContents(),
        ])
        ->all();
}

test('no component validates inline — validation lives in a Form extending BaseForm', function (): void {
    $offenders = collect(formValidationSources())
        ->map(function (string $code): array {
            return collect(FORM_VALIDATION_PATTERNS)
                ->filter(fn (string $pattern): bool => preg_match($pattern, $code) === 1)
                ->values()
                ->all();
        })
        ->filter(fn (array $matched): bool => $matched !== [])
        ->map(fn (array $matched, string $path): string => $path.' → '.implode(' · ', $matched))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
