<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Once;

/*
|--------------------------------------------------------------------------
| Golden rule: the brand name has ONE source
|--------------------------------------------------------------------------
| Born 2026-10-03. The name was spelled out 140 times — 118 in lang files
| and 22 in views — and it is being renamed because the current one is
| taken. A rename like that is a row in `companies`, never a sweep through
| the repository, so nothing may print the literal again.
|
| Fix when red: in a lang string write `:brand` (the translator fills it in
| from the Company row); in a view call `Company::brand()`.
*/

/** @return list<string> */
function brandOffendersIn(string $path, string $brand): array
{
    $found = [];

    foreach (File::allFiles(base_path($path)) as $file) {
        $contents = File::get($file->getPathname());

        foreach (explode("\n", $contents) as $number => $line) {
            if (! str_contains($line, $brand)) {
                continue;
            }

            // A comment names the feature ("Ask AtendIa") and never reaches a
            // screen; the legal-name placeholder is an EXAMPLE, not the brand.
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '{{--')
                || str_starts_with($trimmed, '/*') || str_contains($line, $brand.' S.A.')) {
                continue;
            }

            $found[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
        }
    }

    return $found;
}

test('no translation spells the brand out', function (): void {
    expect(brandOffendersIn('lang', 'AtendIa'))->toBe([]);
});

test('no view spells the brand out', function (): void {
    expect(brandOffendersIn('resources/views', 'AtendIa'))->toBe([]);
});

/*
| Guards the guardian: a rule that only forbids is worth nothing if the
| supported way does not work. `:brand` has to come back filled in.
*/
test('a translation asking for the brand gets it from the company row', function (): void {
    app('translator')->addLines(['probe.brand' => 'Hola, soy :brand.'], 'es');

    expect(__('probe.brand'))->toBe('Hola, soy '.Company::brand().'.')
        ->and(Company::brand())->not->toBe('');
});

/*
| The whole point: renaming is one field. The current name is taken, so this
| is the day it has to be proven and not assumed.
*/
test('renaming the company renames the product everywhere', function (): void {
    app('translator')->addLines(['probe.brand' => 'Gana con :brand'], 'es');

    Once::flush();
    $before = Company::brand();

    expect(__('probe.brand'))->toBe('Gana con '.$before);

    Company::factory()->create(['brand_name' => 'Conversa']);

    // `current()` memoises per request: a real rename lands on the next one.
    Once::flush();

    expect(Company::brand())->toBe('Conversa')
        ->and(__('probe.brand'))->toBe('Gana con Conversa');
})->uses(RefreshDatabase::class);
