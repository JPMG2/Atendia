<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Re-authentication for the sensitive account actions (email, password,
 * closing). Throttled per action so no settings card can become a quiet
 * oracle to brute-force the password from inside a stolen session.
 */
trait ConfirmsCurrentPassword
{
    /** @throws ValidationException */
    protected function confirmCurrentPassword(string $password, string $action, string $field = 'current_password'): void
    {
        $user = Auth::user();
        $key = "confirm-password:{$action}:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                $field => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([$field => __('validation.current_password')]);
        }

        RateLimiter::clear($key);
    }
}
