<?php
require __DIR__.'/vendor/autoload.php'; require __DIR__.'/tests/Support/CommentScanner.php';
use Tests\Support\CommentScanner as S;
$debt = require __DIR__.'/tests/Feature/comment_debt.php';
$still = [];
foreach ($debt as $rel) {
    $p = __DIR__.'/'.$rel;
    if (! is_file($p)) continue;
    foreach (S::commentsIn($rel, file_get_contents($p)) as $c) {
        if (S::isSpanish($c) || (S::judgesLength($rel) && S::isTooLong($c))) { $still[] = $rel; break; }
    }
}
$head = file_get_contents(__DIR__.'/tests/Feature/comment_debt.php');
$head = substr($head, 0, strpos($head, 'return ['));
$body = $head."return [\n";
foreach ($still as $p) $body .= "    '".$p."',\n";
$body .= "];\n";
file_put_contents(__DIR__.'/tests/Feature/comment_debt.php', $body);
printf("deuda: %d -> %d  (limpiados: %d)\n", count($debt), count($still), count($debt) - count($still));
