<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Single-file comment check (layer C)
|--------------------------------------------------------------------------
| Entry point for the PostToolUse hook. It reuses the very scanner the
| guardian test runs on, so the two layers can never drift apart.
|
| Usage: php tests/Support/comment_check.php <path relative to the project>
*/

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/CommentScanner.php';

use Tests\Support\CommentScanner;

$relative = $argv[1] ?? '';
$absolute = __DIR__.'/../../'.$relative;

if ($relative === '' || ! is_file($absolute)) {
    exit(0);
}

// Files frozen the day the rule landed are not judged yet.
if (in_array($relative, require __DIR__.'/../Feature/comment_debt.php', true)) {
    exit(0);
}

$problems = [];

foreach (CommentScanner::commentsIn($relative, (string) file_get_contents($absolute)) as $comment) {
    $first = trim(explode("\n", trim($comment))[0]);

    if (CommentScanner::isSpanish($comment)) {
        $problems[] = 'Spanish comment: '.$first;
    }

    if (CommentScanner::judgesLength($relative) && CommentScanner::isTooLong($comment)) {
        $problems[] = 'Comment too long: '.$first;
    }
}

if ($problems === []) {
    exit(0);
}

fwrite(STDERR, "Comment golden rules broken in {$relative}:\n");

foreach (array_unique($problems) as $problem) {
    fwrite(STDERR, '  - '.$problem."\n");
}

fwrite(STDERR, "\nEnglish, why-not-what, max 3 lines inline / 5 of docblock prose.\nRule: .ai/guidelines/comentarios.md\n");

exit(1);
