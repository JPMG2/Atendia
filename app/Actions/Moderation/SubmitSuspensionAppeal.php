<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Mail\ModerationAppeal;
use App\Messaging\Channels\Email;
use App\Models\Business;

/** One appeal per suspension (a false positive, say a lingerie catalog): it waits on the admin's desk. */
class SubmitSuspensionAppeal
{
    public function handle(Business $business, string $message): bool
    {
        if (! $business->isSuspended() || $business->appealed_at !== null) {
            return false;
        }

        $business->forceFill(['appeal_message' => $message, 'appealed_at' => now()])->save();

        $admin = (string) config('atendia.admin_email');

        if ($admin !== '') {
            (new Email($business, [$admin], ModerationAppeal::class))->send();
        }

        return true;
    }
}
