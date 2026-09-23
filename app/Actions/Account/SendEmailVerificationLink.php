<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountEmailVerification;
use App\Messaging\Channels\Email;
use App\Models\User;

class SendEmailVerificationLink
{
    public function handle(User $user): void
    {
        (new Email($user, [$user->email], AccountEmailVerification::class))->send();
    }
}
