<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule — every plan figure comes from ONE place: the plans table
|--------------------------------------------------------------------------
| Prices, caps and trial days live in the `plans` rows and are read through
| App\Classes\Main\Plan. A figure typed into a view or a translation is how
| the landing once promised 5 numbers while billing gave 4 (2026-09-25).
*/

/** Digits glued to a plan dial: the shape of a cap or a trial typed by hand. */
const PLAN_FIGURE_PATTERN = '/\d[\d.]*\s+(conversaciones con IA|n[uú]meros? de WhatsApp|minutos de audio|consultas al mes|mensajes por hora|d[ií]as gratis|d[ií]as el plan)/iu';

/** @return list<string> */
function planSourceFiles(string ...$directories): array
{
    return collect($directories)
        ->flatMap(fn (string $directory): array => File::allFiles(base_path($directory)))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.php'))
        ->map(fn ($file): string => $file->getPathname())
        ->values()
        ->all();
}

test('nothing reads plans or the trial from config anymore', function (): void {
    $offenders = collect(planSourceFiles('app', 'resources/views', 'database', 'routes', 'config'))
        ->filter(fn (string $path): bool => preg_match('/atendia\.(plans|trial)\b/', File::get($path)) === 1)
        ->map(fn (string $path): string => str_replace(base_path().'/', '', $path))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view or translation types a plan figure by hand', function (): void {
    $offenders = collect(planSourceFiles('lang', 'resources/views'))
        ->filter(fn (string $path): bool => preg_match(PLAN_FIGURE_PATTERN, File::get($path)) === 1)
        ->map(fn (string $path): string => str_replace(base_path().'/', '', $path))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});

test('no view types a plan price by hand', function (): void {
    $offenders = collect(planSourceFiles('resources/views'))
        ->filter(fn (string $path): bool => preg_match("/'\\$\d+'/", File::get($path)) === 1)
        ->map(fn (string $path): string => str_replace(base_path().'/', '', $path))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
