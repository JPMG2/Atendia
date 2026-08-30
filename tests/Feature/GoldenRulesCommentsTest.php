<?php

declare(strict_types=1);

use Tests\Support\CommentScanner;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — comments
|--------------------------------------------------------------------------
| Comments and PHPDoc are written in English, short and to the point. Ratchet
| pattern: GREEN today, STRICT on every file that is not in the debt list.
| The debt list is a backlog to empty, never a place to add to.
|
| Rule: .ai/guidelines/comentarios.md
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/** Where source lives. `lang/` is out: that copy is Spanish on purpose. */
const COMMENT_SCANNED_DIRS = [
    'app', 'database', 'tests', 'routes', 'config', 'resources/js', 'resources/views',
];

/**
 * Files still carrying the old comments, frozen the day the rule landed.
 *
 * @return list<string>
 */
function commentDebt(): array
{
    return require __DIR__.'/comment_debt.php';
}

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

/**
 * Files outside the debt list, which the rule applies to in full.
 *
 * @return array<string, string>
 */
function sourcesUnderRule(): array
{
    $debt = commentDebt();

    return array_diff_key(scannedSources(), array_flip($debt));
}

test('comments are written in English', function (): void {
    $offenders = [];

    foreach (sourcesUnderRule() as $path => $contents) {
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

    foreach (sourcesUnderRule() as $path => $contents) {
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

test('the debt list only ever shrinks', function (): void {
    $sources = scannedSources();

    // An entry that no longer offends has been fixed: it has to leave the list,
    // or the file silently stops being guarded from then on.
    $settled = collect(commentDebt())
        ->filter(fn (string $path): bool => isset($sources[$path]))
        ->reject(function (string $path) use ($sources): bool {
            foreach (CommentScanner::commentsIn($path, $sources[$path]) as $comment) {
                if (CommentScanner::isSpanish($comment) || CommentScanner::isTooLong($comment)) {
                    return true;
                }
            }

            return false;
        })
        ->values()
        ->all();

    expect($settled)->toBe([], 'These files are already clean: drop them from tests/Feature/comment_debt.php so the guardian starts protecting them.');
});

test('the debt list points at files that exist', function (): void {
    $sources = scannedSources();

    $missing = collect(commentDebt())
        ->reject(fn (string $path): bool => isset($sources[$path]))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Stale entries in tests/Feature/comment_debt.php: the file is gone or was renamed.');
});
