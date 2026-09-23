<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;

class CancelEmailChange
{
    /**
     * With a hash (the "wasn't me" link) it only cancels the change that
     * link was mailed for; from the settings screen any pending one goes.
     */
    public function handle(User $user, ?string $hash = null): bool
    {
        $pending = $user->pending_email;

        if ($pending === null || ($hash !== null && ! hash_equals(sha1($pending), $hash))) {
            return false;
        }

        $user->pending_email = null;
        $user->save();

        return true;
    }
}
