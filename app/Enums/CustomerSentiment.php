<?php

declare(strict_types=1);

namespace App\Enums;

/** How the customer left the stretch of the thread that was analyzed. */
enum CustomerSentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
}
