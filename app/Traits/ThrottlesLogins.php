<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The sign-in brake, shared by the web form and the mobile API so the two
 * doors can never drift apart. The host is a FormRequest with an `email` field.
 */
trait ThrottlesLogins
{
    /** How long the wider nets remember a failure: a quarter of an hour. */
    private const int WIDER_DECAY_SECONDS = 900;

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $blockedKey = null;

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            $blockedKey = $this->throttleKey();
        }

        foreach ($this->widerLimits() as $key => $max) {
            if ($blockedKey === null && RateLimiter::tooManyAttempts($key, $max)) {
                $blockedKey = $key;
            }
        }

        if ($blockedKey === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($blockedKey);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function recordFailedLogin(): void
    {
        RateLimiter::hit($this->throttleKey());

        foreach (array_keys($this->widerLimits()) as $key) {
            RateLimiter::hit($key, self::WIDER_DECAY_SECONDS);
        }
    }

    private function clearFailedLogins(): void
    {
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->emailKey());
    }

    /**
     * The slower, wider nets behind the per-minute one: a botnet that spreads
     * one account over many addresses, and one address that walks many accounts.
     *
     * @return array<string, int> key => failed attempts allowed per window
     */
    private function widerLimits(): array
    {
        return [
            $this->emailKey() => 25,
            'login-ip:'.$this->ip() => 30,
        ];
    }

    private function emailKey(): string
    {
        return 'login-email:'.Str::transliterate(Str::lower($this->string('email')));
    }
}
