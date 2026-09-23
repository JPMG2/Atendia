<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountPasswordReset;
use App\Messaging\Channels\Email;
use App\Models\User;

class SendPasswordResetLink
{
    public function handle(User $user, string $token): void
    {
        (new Email($user, [$user->email], AccountPasswordReset::class, [$token]))->send();
    }
}
