<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Mail\BusinessSuspended;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\BusinessSuspended as BusinessSuspendedMessage;
use App\Models\Business;

/**
 * The kill switch: the assistant goes silent and the owner is told why, by
 * mail and WhatsApp. Only the admin lifts it (LiftSuspension).
 */
class SuspendBusiness
{
    public function handle(Business $business, string $reason): bool
    {
        if ($business->isSuspended()) {
            return false;
        }

        $business->forceFill(['suspended_at' => now(), 'suspension_reason' => $reason])->save();

        $emails = $business->users()->pluck('email')->filter()->values()->all();

        if ($emails !== []) {
            (new Email($business, $emails, BusinessSuspended::class))->send();
        }

        if (strlen($business->ownerWhatsAppDigits()) >= 8) {
            (new WhatsApp($business, [$business->ownerWhatsAppDigits()], BusinessSuspendedMessage::class))->send();
        }

        return true;
    }
}
