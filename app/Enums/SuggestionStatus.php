<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a "your assistant did not know this" item stands with the owner. */
enum SuggestionStatus: string
{
    case Pending = 'pending';
    case Taught = 'taught';
    case Dismissed = 'dismissed';
}
