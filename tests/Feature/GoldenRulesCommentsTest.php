<?php

declare(strict_types=1);

use Tests\Support\CommentScanner;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — comments
|--------------------------------------------------------------------------
| Comments and PHPDoc are written in English, short and to the point. The
| backlog this started with is empty, so the rule applies to every file with
| no exceptions left.
|
| Rule: .ai/guidelines/comentarios.md
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/** Where source lives. `lang/` is out: that copy is Spanish on purpose. */
const COMMENT_SCANNED_DIRS = [
    'app', 'database', 'tests', 'routes', 'config', 'resources/js', 'resources/views',
];

/**
 * Every scanned source file, keyed by its path relative to the project root.
 *
 * @return array<string, string>
 */
function scannedSources(): array
{
    $sources = [];

    foreach (COMMENT_SCANNED_DIRS as $dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($dir))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! preg_match('/\.(php|js)$/', $file->getFilename())) {
                continue;
            }

            $relative = str_replace(base_path().'/', '', $file->getPathname());
            $sources[$relative] = (string) file_get_contents($file->getPathname());
        }
    }

    return $sources;
}

test('comments are written in English', function (): void {
    $offenders = [];

    foreach (scannedSources() as $path => $contents) {
        foreach (CommentScanner::commentsIn($path, $contents) as $comment) {
            if (CommentScanner::isSpanish($comment)) {
                $offenders[$path] = trim(explode("\n", trim($comment))[0]);
                break;
            }
        }
    }

    expect($offenders)->toBe([], 'Spanish comments found. Comments and PHPDoc go in English — only the copy the customer reads stays in Spanish.');
});

test('comments stay short', function (): void {
    $offenders = [];

    foreach (scannedSources() as $path => $contents) {
        if (! CommentScanner::judgesLength($path)) {
            continue;
        }

        foreach (CommentScanner::commentsIn($path, $contents) as $comment) {
            if (CommentScanner::isTooLong($comment)) {
                $offenders[$path] = trim(explode("\n", trim($comment))[0]);
                break;
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        'Comments longer than the rule: %d lines inline, %d lines of docblock prose. Say why in fewer words, or move the essay to .ai/guidelines/.',
        CommentScanner::MAX_INLINE_LINES,
        CommentScanner::MAX_DOCBLOCK_PROSE,
    ));
});
