<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Actions\Admin\RaisePlatformEvent;
use App\Enums\MentionOfferState;
use App\Enums\MentionStatus;
use App\Enums\PlatformEventKind;
use App\Models\Business;
use App\Models\BusinessMention;
use App\Models\MentionGoal;

/**
 * The owner says yes. The offer is resolved again here: the card is a picture
 * of a moment, and a stale one must not create a mention for a goal that was
 * switched off or already answered. The consent is the click, stored with its date.
 */
class AcceptMention
{
    public function handle(Business $business): ?BusinessMention
    {
        $offer = app(ResolveMentionOffer::class)->handle($business);

        if ($offer === null || $offer->state !== MentionOfferState::Offer) {
            return null;
        }

        $mention = $business->mentions()->create([
            'social_network_id' => $offer->platformLink->social_network_id,
            'goal_threshold' => $offer->threshold,
            'conversations' => $business->conversationsThisMonth(),
            'status' => MentionStatus::Pending,
            'business_account_url' => $offer->businessLink->url,
            'platform_accounts' => $offer->accounts,
            'caption' => $offer->goal->captionFor($business->name),
            'answered_at' => now(),
        ]);

        app(RaisePlatformEvent::class)->handle(
            PlatformEventKind::MentionAccepted,
            $business->id,
            $business->name.' · '.MentionGoal::achievement($offer->threshold),
            'mention:'.$mention->id,
        );

        return $mention;
    }
}
