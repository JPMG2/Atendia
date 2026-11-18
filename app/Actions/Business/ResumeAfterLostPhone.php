<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Mail\LostPhonePauseChanged;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\User;

/**
 * Lifts the lost-phone pause. The caller has already re-checked the password;
 * the receipt by e-mail is what warns the owner if it was not her. The number
 * comes back with a fresh QR scan, never on its own.
 */
class ResumeAfterLostPhone
{
    public function handle(Business $business, User $by): void
    {
        if (! $business->isPausedForLostPhone()) {
            return;
        }

        $business->forceFill(['lost_phone_paused_at' => null, 'lost_phone_paused_by' => null])->save();

        (new Email($by, [$by->email], LostPhonePauseChanged::class, [false]))->send();
    }
}
