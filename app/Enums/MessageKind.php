<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a thread row IS: a real message, or an internal note the customer
 * (and the assistant's memory) never sees.
 */
enum MessageKind: string
{
    case Message = 'message';
    case Note = 'note';
}
