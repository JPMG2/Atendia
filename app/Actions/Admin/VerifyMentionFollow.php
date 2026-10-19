<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\MentionStatus;
use App\Models\BusinessMention;
use App\Models\User;

/**
 * The admin checked and the business follows the platform's account: the
 * mention moves to the publishing queue. Only a pending one can move, so a
 * double click or a stale tab cannot skip a step.
 */
class VerifyMentionFollow
{
    public function handle(int $id, User $by): ?BusinessMention
    {
        $mention = BusinessMention::query()->where('status', MentionStatus::Pending)->find($id);

        if ($mention === null) {
            return null;
        }

        $mention->update(['status' => MentionStatus::Ready, 'verified_at' => now(), 'verified_by' => $by->id]);
        app(ResolvePlatformEvent::class)->byKey('mention:'.$mention->id);

        return $mention;
    }
}
