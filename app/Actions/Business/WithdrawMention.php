<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Actions\Admin\ResolvePlatformEvent;
use App\Enums\MentionStatus;
use App\Models\Business;
use App\Models\BusinessMention;

/**
 * The owner changes their mind before the post is out. It counts as a "no", and
 * a published mention cannot be withdrawn here: it is already public.
 */
class WithdrawMention
{
    public function handle(Business $business): ?BusinessMention
    {
        $mention = $business->mentions()
            ->whereIn('status', [MentionStatus::Pending, MentionStatus::NotFollowing, MentionStatus::Ready])
            ->latest('id')
            ->first();

        if ($mention === null) {
            return null;
        }

        $mention->update(['status' => MentionStatus::Withdrawn, 'answered_at' => now()]);
        app(ResolvePlatformEvent::class)->byKey('mention:'.$mention->id);

        return $mention;
    }
}
