<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationSeverity: string
{
    case Clean = 'clean';
    // Adult content a lingerie catalog can trip too: the file is refused and the admin reviews, no pause.
    case Rejected = 'rejected';
    // Minors, or adult content scored beyond doubt: the business is suspended on the spot.
    case Severe = 'severe';
    // Moderation could not answer: with zero tolerance, nothing unchecked gets in.
    case Unavailable = 'unavailable';
}
