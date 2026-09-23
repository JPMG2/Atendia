<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Mail\AccountTwoFactorChanged;
use App\Messaging\Channels\Email;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ConfirmWhatsAppTwoFactor
{
    private const int MAX_ATTEMPTS = 5;

    /** True once the code matches; five misses burn the code and a new one is needed. */
    public function handle(User $user, string $code): bool
    {
        $key = SendWhatsAppSetupCode::cacheKey($user);
        $setup = Cache::get($key);

        if ($setup === null || $setup['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (! hash_equals($setup['hash'], hash('sha256', $code))) {
            $setup['attempts']++;
            Cache::put($key, $setup, now()->addMinutes(SendWhatsAppSetupCode::LIFETIME_MINUTES));

            return false;
        }

        Cache::forget($key);
        $user->two_factor_whatsapp_at = now();
        $user->save();

        (new Email($user, [$user->email], AccountTwoFactorChanged::class, [true]))->send();

        return true;
    }
}
