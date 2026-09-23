<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountEmailChangeNotice;
use App\Mail\AccountEmailChangeRequested;
use App\Messaging\Channels\Email;
use App\Models\User;

/**
 * OWASP's email-change flow: the new address waits as pending until its
 * inbox confirms it, and the current one hears about the request so the
 * real owner can stop a hijack.
 */
class RequestEmailChange
{
    public function handle(User $user, string $newEmail): User
    {
        $user->pending_email = mb_strtolower($newEmail);
        $user->save();

        (new Email($user, [$user->pending_email], AccountEmailChangeRequested::class))->send();
        (new Email($user, [$user->email], AccountEmailChangeNotice::class, [$user->pending_email]))->send();

        return $user;
    }
}
