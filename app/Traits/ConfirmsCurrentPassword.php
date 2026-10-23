<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Re-authentication for the sensitive account actions (email, password,
 * closing). ONE throttle per account, not per action: with a bucket for each
 * card a stolen session got five guesses at every one of them, so the cards
 * (and the app's endpoints) would have been a wider oracle than any single one.
 */
trait ConfirmsCurrentPassword
{
    /** @throws ValidationException */
    protected function confirmCurrentPassword(string $password, string $field = 'current_password'): void
    {
        $user = Auth::user();
        $key = "confirm-password:{$user->id}";

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
