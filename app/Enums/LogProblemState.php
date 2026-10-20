<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a log problem stands: waiting on somebody, handled, or ignored on purpose. */
enum LogProblemState: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Muted = 'muted';
}
