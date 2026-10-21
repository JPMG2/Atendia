<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Account\UseRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The code gate of DeviceChallenge for an app that has no session: the
 * challenge lives in the cache under an opaque id, bound to the install that
 * asked. Same rules as the web: a hash of the code, ten minutes, five tries.
 * The tries are counted with an atomic increment so parallel guesses cannot
 * slip past the limit.
 */
class MobileDeviceChallenge
{
    private const int LIFETIME_MINUTES = 10;

    private const int MAX_ATTEMPTS = 5;

    private const int MAX_RESENDS = 3;

    /**
     * @return array{id: string, via_whatsapp: bool, whatsapp_failed: bool, destination: ?string, expires_in: int}
     */
    public static function start(User $user, string $deviceId, string $deviceName): array
    {
        $id = Str::random(48);

        return self::issue($user, $id, $deviceId, $deviceName);
    }

    /**
     * @return array{user: User, device_id: string, device_name: string}|null
     */
    public static function verify(string $id, string $code, string $deviceId): ?array
    {
        $challenge = self::countedAttempt($id, $deviceId);

        if ($challenge === null || ! hash_equals($challenge['code_hash'], hash('sha256', $code))) {
            return null;
        }

        return self::resolve($id, $challenge);
    }

    /**
     * @return array{user: User, device_id: string, device_name: string}|null
     */
    public static function verifyRecovery(string $id, string $code, string $deviceId): ?array
    {
        $challenge = self::countedAttempt($id, $deviceId);
        $user = $challenge === null ? null : User::query()->find($challenge['user_id']);

        if ($user === null || ! app(UseRecoveryCode::class)->handle($user, $code)) {
            return null;
        }

        return self::resolve($id, $challenge);
    }

    /**
     * @return array{id: string, via_whatsapp: bool, whatsapp_failed: bool, destination: ?string, expires_in: int}|null
     */
    public static function resend(string $id, string $deviceId): ?array
    {
        $challenge = self::load($id);

        if ($challenge === null || ! hash_equals($challenge['device_id'], $deviceId)) {
            return null;
        }

        Cache::add(self::resendsKey($id), 0, now()->addMinutes(self::LIFETIME_MINUTES));

        if (Cache::increment(self::resendsKey($id)) > self::MAX_RESENDS) {
            return null;
        }

        $user = User::query()->find($challenge['user_id']);

        if ($user === null) {
            self::forget($id);

            return null;
        }

        return self::issue($user, $id, $challenge['device_id'], $challenge['device_name']);
    }

    /**
     * @return array{id: string, via_whatsapp: bool, whatsapp_failed: bool, destination: ?string, expires_in: int}
     */
    private static function issue(User $user, string $id, string $deviceId, string $deviceName): array
    {
        $issued = DeviceChallenge::issueCode($user);

        Cache::put(self::key($id), [
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'code_hash' => $issued['code_hash'],
            'via_whatsapp' => $issued['via_whatsapp'],
            'whatsapp_failed' => $issued['whatsapp_failed'],
        ], now()->addMinutes(self::LIFETIME_MINUTES));

        Cache::put(self::attemptsKey($id), 0, now()->addMinutes(self::LIFETIME_MINUTES));

        return [
            'id' => $id,
            'via_whatsapp' => $issued['via_whatsapp'],
            'whatsapp_failed' => $issued['whatsapp_failed'],
            'destination' => $issued['via_whatsapp'] ? $user->maskedSecondFactorPhone() : $user->maskedEmail(),
            'expires_in' => self::LIFETIME_MINUTES * 60,
        ];
    }

    /**
     * Spends one try and returns the challenge only while tries remain and the
     * call comes from the install that asked.
     *
     * @return array<string, mixed>|null
     */
    private static function countedAttempt(string $id, string $deviceId): ?array
    {
        $challenge = self::load($id);

        if ($challenge === null || ! hash_equals($challenge['device_id'], $deviceId)) {
            return null;
        }

        if (Cache::increment(self::attemptsKey($id)) > self::MAX_ATTEMPTS) {
            self::forget($id);

            return null;
        }

        return $challenge;
    }

    /**
     * @param  array<string, mixed>  $challenge
     * @return array{user: User, device_id: string, device_name: string}|null
     */
    private static function resolve(string $id, array $challenge): ?array
    {
        $user = User::query()->find($challenge['user_id']);

        self::forget($id);

        return $user === null ? null : [
            'user' => $user,
            'device_id' => $challenge['device_id'],
            'device_name' => $challenge['device_name'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function load(string $id): ?array
    {
        $challenge = Cache::get(self::key($id));

        return is_array($challenge) ? $challenge : null;
    }

    private static function forget(string $id): void
    {
        Cache::forget(self::key($id));
        Cache::forget(self::attemptsKey($id));
        Cache::forget(self::resendsKey($id));
    }

    private static function key(string $id): string
    {
        return 'mobile-challenge:'.hash('sha256', $id);
    }

    private static function attemptsKey(string $id): string
    {
        return self::key($id).':attempts';
    }

    private static function resendsKey(string $id): string
    {
        return self::key($id).':resends';
    }
}
