<?php

declare(strict_types=1);

/*
| Every version the customers can see, newest first. The FIRST row is the one
| the landing footer and both panels print; its words live in
| lang/es/releases.php under the same number. Which number moves is decided
| per change (.ai/guidelines/atendia.md) and the commit hook enforces it.
*/

return [
    ['version' => '1.2.2', 'date' => '2026-11-20', 'kind' => 'patch'],
    ['version' => '1.2.1', 'date' => '2026-11-20', 'kind' => 'patch'],
    ['version' => '1.2.0', 'date' => '2026-11-20', 'kind' => 'minor'],
    ['version' => '1.1.0', 'date' => '2026-11-20', 'kind' => 'minor'],
    ['version' => '1.0.0', 'date' => '2026-11-19', 'kind' => 'major'],
];
