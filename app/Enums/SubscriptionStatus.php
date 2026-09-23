<?php

declare(strict_types=1);

namespace App\Enums;

/** Where the business stands with its payments to Atendia. */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    // Past the payment date, inside the grace days: still answering.
    case PastDue = 'past_due';
    // Grace ran out unpaid: the assistant stops, nothing is erased.
    case Paused = 'paused';
}
