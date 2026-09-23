<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;

class RestoreAccount
{
    /** Brings the account and its business back whole, only inside the restore window. */
    public function handle(User $user): bool
    {
        if (! $user->isRestorable()) {
            return false;
        }

        $user->restore();
        $user->business()->onlyTrashed()->first()?->restore();

        return true;
    }
}
