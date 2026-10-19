<?php

declare(strict_types=1);

namespace App\Enums;

/** What the mention card on the owner's home has to say right now. */
enum MentionOfferState: string
{
    /** A goal was reached and the owner has not answered. */
    case Offer = 'offer';

    /** A goal was reached but the business has no account where ours is. */
    case NeedsNetwork = 'needs_network';

    case Pending = 'pending';
    case NotFollowing = 'not_following';
    case Ready = 'ready';
}
