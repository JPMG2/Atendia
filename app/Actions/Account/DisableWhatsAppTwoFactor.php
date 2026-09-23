<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountTwoFactorChanged;
use App\Messaging\Channels\Email;
use App\Models\User;

class DisableWhatsAppTwoFactor
{
    public function handle(User $user): void
    {
        $user->two_factor_whatsapp_at = null;
        // The backup codes only make sense next to the second step.
        $user->two_factor_recovery_codes = null;
        $user->save();

        (new Email($user, [$user->email], AccountTwoFactorChanged::class, [false]))->send();
    }
}
