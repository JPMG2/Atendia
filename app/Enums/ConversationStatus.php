<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a thread stands. Open is the assistant's territory; Team means a
 * human was called and the assistant stays quiet in THAT thread; Customer
 * and Resolved arrive with the composer phase but the column is born whole.
 */
enum ConversationStatus: string
{
    case Open = 'open';
    case Team = 'team';
    case Customer = 'customer';
    case Resolved = 'resolved';
}
