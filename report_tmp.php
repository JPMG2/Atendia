<?php
require __DIR__.'/vendor/autoload.php'; require __DIR__.'/tests/Support/CommentScanner.php';
use Tests\Support\CommentScanner as S;
$filter = $argv[1] ?? '';
foreach (require __DIR__.'/tests/Feature/comment_debt.php' as $rel) {
    if ($filter !== '' && ! str_starts_with($rel, $filter)) continue;
    $p = __DIR__.'/'.$rel; if (! is_file($p)) continue;
    $out = [];
    foreach (S::commentsIn($rel, file_get_contents($p)) as $c) {
        $f = []; if (S::isSpanish($c)) $f[]='ES'; if ((S::judgesLength($rel) && S::isTooLong($c))) $f[]='LONG';
        if ($f) $out[] = '  ['.implode('+',$f).'] '.str_replace("\n", "\n      ", trim($c));
    }
    if ($out) { echo "\n### $rel\n".implode("\n", $out)."\n"; }
}
