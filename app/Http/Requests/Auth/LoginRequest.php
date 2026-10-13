<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Actions\Account\RestoreAccount;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** How long the wider nets remember a failure: a quarter of an hour. */
    private const int WIDER_DECAY_SECONDS = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))
            && ! $this->restoreClosedAccount()) {
            RateLimiter::hit($this->throttleKey());

            foreach (array_keys($this->widerLimits()) as $key) {
                RateLimiter::hit($key, self::WIDER_DECAY_SECONDS);
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

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

    /**
     * Signing in with the right password inside the restore window brings a
     * closed account back (Notion-style): coming back must not need a mail.
     */
    private function restoreClosedAccount(): bool
    {
        $closed = User::restorableByEmail((string) $this->string('email'));

        if ($closed === null || ! Hash::check((string) $this->string('password'), $closed->password)) {
            return false;
        }

        return app(RestoreAccount::class)->handle($closed)
            && Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));
    }

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
}
