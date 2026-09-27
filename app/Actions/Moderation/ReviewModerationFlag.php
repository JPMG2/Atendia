<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Models\ModerationFlag;
use App\Models\User;

/** A refused file the admin looked at: it leaves the queue, the suspension (if any) stays. */
class ReviewModerationFlag
{
    public function handle(int $flagId, User $admin): void
    {
        $flag = ModerationFlag::query()->findOrFail($flagId);
        $flag->forceFill(['reviewed_at' => now(), 'reviewed_by' => $admin->id])->save();
    }
}
