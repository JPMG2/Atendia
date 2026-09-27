<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountClosed;
use App\Messaging\Channels\Email;
use App\Models\User;

/**
 * Closing is a soft delete of the account AND its business: nobody signs
 * in, the assistant stops answering, and not one row is erased — the data
 * is worth keeping, and the owner may come back.
 */
class CloseAccount
{
    public function handle(User $user): void
    {
        // Closing takes the whole business with it: only its owner holds that key.
        // An agent leaves when the owner removes them from "Equipo".
        abort_if($user->isAgent(), 403);

        $user->business?->delete();
        $user->pending_email = null;
        $user->save();
        $user->delete();

        (new Email($user, [$user->email], AccountClosed::class))->send();
    }
}
