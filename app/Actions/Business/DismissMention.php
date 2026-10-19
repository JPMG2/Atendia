<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\MentionOfferState;
use App\Enums\MentionStatus;
use App\Models\Business;
use App\Models\BusinessMention;

/**
 * "Ahora no" is an answer: it is stored with the goal it was said to, so the
 * offer only comes back for a bigger one and after the pause.
 */
class DismissMention
{
    public function handle(Business $business): ?BusinessMention
    {
        $offer = app(ResolveMentionOffer::class)->handle($business);

        if ($offer === null || ! in_array($offer->state, [MentionOfferState::Offer, MentionOfferState::NeedsNetwork], true)) {
            return null;
        }

        return $business->mentions()->create([
            'goal_threshold' => $offer->threshold,
            'conversations' => $business->conversationsThisMonth(),
            'status' => MentionStatus::Dismissed,
            'answered_at' => now(),
        ]);
    }
}
