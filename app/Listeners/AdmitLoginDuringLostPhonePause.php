<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Middleware\EndSessionsBeforeLostPhonePause;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * A login made after the pause already passed the new password and the
 * device code, so its session may stay: that is how the owner gets back in
 * to lift it. Remember-me cookies from before cannot get here: the pause
 * rotated every remember token of the team.
 */
class AdmitLoginDuringLostPhonePause
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            EndSessionsBeforeLostPhonePause::admit($event->user->business);
        }
    }
}
