<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\MentionOfferState;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\MentionOffered;
use App\Models\Business;

/**
 * Puts the offer on the bell the moment a goal is reached, so the owner hears
 * about it in real time and not only when the home happens to be opened. The
 * card does not depend on it: this is the nudge, the card is the offer.
 */
class RaiseMentionOffer
{
    public function handle(Business $business): void
    {
        $offer = app(ResolveMentionOffer::class)->handle($business);

        if ($offer === null || $offer->goal === null || ! in_array($offer->state, [MentionOfferState::Offer, MentionOfferState::NeedsNetwork], true)) {
            return;
        }

        new Panel($business, [], MentionOffered::class, [$offer->goal])->send();
    }
}
