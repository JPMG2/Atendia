<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BusinessCreated;
use App\Mail\NewBusinessJoined;
use App\Messaging\Channels\Email;
use App\Models\User;

/**
 * Tells HER that somebody signed up.
 *
 * The welcome goes to the client and she never found out: today the only way
 * to notice a new business is to open the panel and compare against memory.
 * A first client is worth a phone call the same day, not next week.
 */
class NotifyPlatformOfNewBusiness
{
    public function handle(BusinessCreated $event): void
    {
        // Her own account carries the mail: it is the one row always there,
        // and the Company screen may never have been saved.
        $admin = User::where('email', (string) config('atendia.admin_email'))->first();

        if ($admin === null) {
            return;
        }

        (new Email($admin, [(string) $admin->email], NewBusinessJoined::class, [$event->business]))->send();
    }
}
