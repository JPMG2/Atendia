<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Models\Business;
use App\Models\ModerationFlag;
use App\Models\User;

/** The admin's call after reviewing: the assistant speaks again and the business's flags close. */
class LiftSuspension
{
    public function handle(int $businessId, User $admin): Business
    {
        $business = Business::query()->findOrFail($businessId);
        $business->forceFill(['suspended_at' => null, 'suspension_reason' => null, 'appeal_message' => null, 'appealed_at' => null])->save();

        ModerationFlag::query()
            ->where('business_id', $business->id)
            ->whereNull('reviewed_at')
            ->update(['reviewed_at' => now(), 'reviewed_by' => $admin->id]);

        return $business;
    }
}
