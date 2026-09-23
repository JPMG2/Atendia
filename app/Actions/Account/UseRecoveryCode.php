<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;

class UseRecoveryCode
{
    /** A match is spent on the spot: every backup code opens the door once. */
    public function handle(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];
        $hash = GenerateRecoveryCodes::hash($code);

        if (! in_array($hash, $hashes, true)) {
            return false;
        }

        $user->two_factor_recovery_codes = array_values(array_diff($hashes, [$hash]));
        $user->save();

        return true;
    }
}
