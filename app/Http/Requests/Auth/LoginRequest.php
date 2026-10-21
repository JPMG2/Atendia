<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Actions\Account\RestoreAccount;
use App\Models\User;
use App\Traits\ThrottlesLogins;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    use ThrottlesLogins;

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
            $this->recordFailedLogin();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->clearFailedLogins();
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
}
