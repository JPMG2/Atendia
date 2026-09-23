<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Before WhatsApp codes guard the login, the owner proves the number is
 * theirs with one of them: a typo in the contact card must never lock the
 * account out. Only the hash is kept, for ten minutes.
 */
class SendWhatsAppSetupCode
{
    public const int LIFETIME_MINUTES = 10;

    /** False when there is no number or WhatsApp did not answer. */
    public function handle(User $user): bool
    {
        $phone = $user->secondFactorPhone();

        if ($phone === null) {
            return false;
        }

        $code = (string) random_int(100000, 999999);

        Cache::put(self::cacheKey($user), ['hash' => hash('sha256', $code), 'attempts' => 0], now()->addMinutes(self::LIFETIME_MINUTES));

        return (new WhatsApp($user, [$phone], LoginCode::class, [$code]))->send();
    }

    public static function cacheKey(User $user): string
    {
        return 'two-factor-setup:'.$user->id;
    }
}
