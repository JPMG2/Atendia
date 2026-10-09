<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The shots a human looks at have to show what their name says
|--------------------------------------------------------------------------
| `inDarkMode()` of the browser plugin leaves the page in light, so every
| "-dark" capture made with it was a light page with another file name. The
| class is set by hand instead (or the theme toggle is clicked).
*/

test('no browser test uses inDarkMode(), which leaves the page in light', function (): void {
    $offenders = collect(File::allFiles(base_path('tests/Browser')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->filter(fn (SplFileInfo $file): bool => str_contains(preg_replace('#//.*$#m', '', (string) file_get_contents($file->getPathname())), '->inDarkMode('))
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
