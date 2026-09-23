<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Account\SendEmailVerificationLink;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Every new account gets its verification link on sign-up. Not via
 * MustVerifyEmail on purpose: that would also lock the panel behind the
 * `verified` middleware until the owner clicks, and the tour must not wait.
 */
class SendRegistrationVerification
{
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User && ! $event->user->hasVerifiedEmail()) {
            app(SendEmailVerificationLink::class)->handle($event->user);
        }
    }
}
