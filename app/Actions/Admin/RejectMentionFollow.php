<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\MentionStatus;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\MentionNeedsFollow;
use App\Models\BusinessMention;
use App\Services\Tenant;

/**
 * The business does not follow the platform's account yet. It goes back to the
 * owner with the reason on their bell, and returns to this desk when they say
 * they followed. Nothing is published for an account that does not follow.
 */
class RejectMentionFollow
{
    public function handle(int $id): ?BusinessMention
    {
        $mention = BusinessMention::query()->where('status', MentionStatus::Pending)->find($id);

        if ($mention === null) {
            return null;
        }

        $mention->update(['status' => MentionStatus::NotFollowing]);
        app(ResolvePlatformEvent::class)->byKey('mention:'.$mention->id);

        // The notice belongs to the business: without adopting it the bell lands nowhere.
        app(Tenant::class)->for(
            $mention->business_id,
            fn (): bool => new Panel($mention, [], MentionNeedsFollow::class)->send(),
        );

        return $mention;
    }
}
