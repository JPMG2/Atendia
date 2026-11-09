<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why PHP believes the other side of a chat is a machine. Logged for the
 * diagnosis; the panel shows one plain line whatever the signal was.
 */
enum AutoReplySignal: string
{
    case Echo = 'echo';
    case Phrase = 'phrase';
    case Repeated = 'repeated';
    case Fast = 'fast';
    case Burst = 'burst';
}
