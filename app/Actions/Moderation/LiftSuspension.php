<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Actions\Compliance\RecordComplianceEvent;
use App\Enums\ComplianceEventKind;
use App\Models\Business;
use App\Models\ModerationFlag;
use App\Models\User;

/** The admin's call after reviewing: the assistant speaks again and the business's flags close. */
class LiftSuspension
{
    public function handle(int $businessId, User $admin): Business
    {
        $business = Business::query()->findOrFail($businessId);

        // Before it is blanked: what was the suspension for, and did they appeal?
        $wasFor = $business->suspension_reason;

        $business->forceFill(['suspended_at' => null, 'suspension_reason' => null, 'appeal_message' => null, 'appealed_at' => null])->save();

        ModerationFlag::query()
            ->where('business_id', $business->id)
            ->whereNull('reviewed_at')
            ->update(['reviewed_at' => now(), 'reviewed_by' => $admin->id]);

        app(RecordComplianceEvent::class)->handle($business->id, ComplianceEventKind::SuspensionLifted, $wasFor, $admin->id);

        return $business;
    }
}
