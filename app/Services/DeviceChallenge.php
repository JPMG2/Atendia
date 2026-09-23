<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Account\UseRecoveryCode;
use App\Mail\DeviceChallengeCode;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\LoginCode;
use App\Models\User;

/**
 * The code gate for logins from an unknown device: by e-mail, or by the
 * owner's WhatsApp once two-step verification is on. The session holds only
 * a hash of the code with a short life and few tries.
 */
class DeviceChallenge
{
    private const string SESSION_KEY = 'device_challenge';

    private const int LIFETIME_MINUTES = 10;

    private const int MAX_ATTEMPTS = 5;

    public static function start(User $user, bool $remember): void
    {
        $code = (string) random_int(100000, 999999);

        // Through the house channels like every message: one ritual, one door.
        // A WhatsApp outage must not lock the owner out: the code falls back
        // to the inbox, and the screen says where it actually went.
        $viaWhatsApp = $user->sendsLoginCodesByWhatsApp()
            && (new WhatsApp($user, [(string) $user->secondFactorPhone()], LoginCode::class, [$code]))->send();

        if (! $viaWhatsApp) {
            (new Email($user, [$user->email], DeviceChallengeCode::class, [$code]))->send();
        }

        session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'remember' => $remember,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)->timestamp,
            'attempts' => 0,
            'via_whatsapp' => $viaWhatsApp,
            'whatsapp_failed' => ! $viaWhatsApp && $user->sendsLoginCodesByWhatsApp(),
        ]);
    }

    /** Whose login is waiting on the code, for the screen's masked address. */
    public static function challengedUser(): ?User
    {
        $challenge = session()->get(self::SESSION_KEY);

        return $challenge === null ? null : User::query()->find($challenge['user_id']);
    }

    /** Two-step was on but WhatsApp did not answer: the screen explains the e-mail. */
    public static function whatsAppFailed(): bool
    {
        return (bool) (session()->get(self::SESSION_KEY)['whatsapp_failed'] ?? false);
    }

    /** Which inbox the screen should point at. */
    public static function viaWhatsApp(): bool
    {
        return (bool) (session()->get(self::SESSION_KEY)['via_whatsapp'] ?? false);
    }

    public static function pending(): bool
    {
        $challenge = session()->get(self::SESSION_KEY);

        return $challenge !== null && $challenge['expires_at'] >= now()->timestamp;
    }

    /**
     * The user id to sign in and the remember flag, or null when the code is
     * wrong, expired or out of tries. A spent challenge is always dropped.
     *
     * @return array{user_id: int, remember: bool}|null
     */
    public static function verify(string $code): ?array
    {
        $challenge = session()->get(self::SESSION_KEY);

        if ($challenge === null) {
            return null;
        }

        if ($challenge['expires_at'] < now()->timestamp || $challenge['attempts'] >= self::MAX_ATTEMPTS) {
            self::forget();

            return null;
        }

        if (! hash_equals($challenge['code_hash'], hash('sha256', $code))) {
            $challenge['attempts']++;
            session()->put(self::SESSION_KEY, $challenge);

            return null;
        }

        self::forget();

        return ['user_id' => $challenge['user_id'], 'remember' => (bool) $challenge['remember']];
    }

    /**
     * The phone-lost door: a one-time backup code stands in for the WhatsApp
     * code. Misses spend the same few tries, so it is no side entrance.
     *
     * @return array{user_id: int, remember: bool}|null
     */
    public static function verifyRecovery(string $code): ?array
    {
        $challenge = session()->get(self::SESSION_KEY);
        $user = self::challengedUser();

        if ($challenge === null || $user === null || $challenge['expires_at'] < now()->timestamp || $challenge['attempts'] >= self::MAX_ATTEMPTS) {
            self::forget();

            return null;
        }

        if (! app(UseRecoveryCode::class)->handle($user, $code)) {
            $challenge['attempts']++;
            session()->put(self::SESSION_KEY, $challenge);

            return null;
        }

        self::forget();

        return ['user_id' => $challenge['user_id'], 'remember' => (bool) $challenge['remember']];
    }

    /**
     * A fresh code for the same pending login — the old one dies with it.
     * False when there is nothing to resend (or the account vanished).
     */
    public static function resend(): bool
    {
        $challenge = session()->get(self::SESSION_KEY);

        if ($challenge === null) {
            return false;
        }

        $user = User::query()->find($challenge['user_id']);

        if ($user === null) {
            self::forget();

            return false;
        }

        self::start($user, (bool) $challenge['remember']);

        return true;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
