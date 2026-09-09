<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BusinessConnectionSaved;
use App\Mail\ContactEmailUpdated;
use App\Messaging\Channels\Email;

/**
 * Confirms a CHANGED contact address at its new inbox. The decision lives
 * here once, for every caller of the slice: only a REPLACED address earns
 * it — a first one is the welcome's territory, sent at birth. Not queued:
 * the channel needs the request's locale; the Mailable queues the send.
 */
class SendContactEmailUpdated
{
    public function handle(BusinessConnectionSaved $event): void
    {
        $business = $event->business;

        if (! $event->hadEmail || ! $business->wasChanged('email') || blank($business->email)) {
            return;
        }

        (new Email($business, [$business->email], ContactEmailUpdated::class))->send();
    }
}
