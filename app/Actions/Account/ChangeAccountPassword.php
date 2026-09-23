<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountPasswordChanged;
use App\Messaging\Channels\Email;
use App\Models\User;

class ChangeAccountPassword
{
    /**
     * OWASP: after a password change the owner may end every other session
     * (the device in use survives) and always gets a receipt by mail.
     */
    public function handle(User $user, string $password, bool $logoutOthers, string $currentFingerprint): User
    {
        $user->password = $password;
        $user->password_changed_at = now();
        $user->save();

        if ($logoutOthers) {
            $user->revokeOtherDevices($currentFingerprint);
        }

        (new Email($user, [$user->email], AccountPasswordChanged::class))->send();

        return $user;
    }
}
