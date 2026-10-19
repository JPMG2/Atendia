<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Actions\Admin\RaisePlatformEvent;
use App\Enums\MentionStatus;
use App\Enums\PlatformEventKind;
use App\Models\Business;
use App\Models\BusinessMention;
use App\Models\MentionGoal;

/** The owner followed the account after being told they did not: it goes back to the admin's desk. */
class ConfirmMentionFollow
{
    public function handle(Business $business): ?BusinessMention
    {
        $mention = $business->mentions()->where('status', MentionStatus::NotFollowing)->latest('id')->first();

        if ($mention === null) {
            return null;
        }

        $mention->update(['status' => MentionStatus::Pending]);

        app(RaisePlatformEvent::class)->handle(
            PlatformEventKind::MentionAccepted,
            $business->id,
            $business->name.' · '.MentionGoal::achievement($mention->goal_threshold),
            'mention:'.$mention->id,
        );

        return $mention;
    }
}
