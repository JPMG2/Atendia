<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyAccountEmail
{
    /** The hash ties the link to the address it was mailed to: a later change voids it. */
    public function handle(User $user, string $hash): bool
    {
        if (! hash_equals(sha1((string) $user->email), $hash)) {
            return false;
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return true;
    }
}
