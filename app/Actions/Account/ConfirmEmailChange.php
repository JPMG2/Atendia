<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountEmailUpdated;
use App\Messaging\Channels\Email;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmEmailChange
{
    /**
     * False when the link is stale (another change replaced it) or the
     * address was taken meanwhile: uniqueness is re-checked at the switch,
     * since the request only proved it free back then.
     */
    public function handle(User $user, string $hash): bool
    {
        $pending = $user->pending_email;

        if ($pending === null || ! hash_equals(sha1($pending), $hash)) {
            return false;
        }

        if (User::emailIsTaken($pending, $user->id)) {
            $user->pending_email = null;
            $user->save();

            return false;
        }

        $oldEmail = $user->email;

        $user->forceFill([
            'email' => $pending,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        // A reset link mailed to the old inbox must not outlive the switch.
        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

        (new Email($user, [$user->email], AccountEmailUpdated::class))->send();

        return true;
    }
}
