<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BusinessCreated;
use App\Mail\BusinessWelcome;
use App\Messaging\Channels\Email;

/**
 * Sends the welcome for a freshly created business.
 *
 * Deliberately NOT queued: the channel captures the visitor's locale from the
 * running request, which a worker does not have. The Mailable itself is
 * ShouldQueue, so the actual send still leaves through the queue.
 */
class SendBusinessWelcome
{
    public function handle(BusinessCreated $event): void
    {
        // At creation time the account's address IS the billing email: the
        // business's own contact is only asked steps later.
        (new Email($event->business, [$event->business->billing_email], BusinessWelcome::class))->send();
    }
}
