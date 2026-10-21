<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Actions\Account\RestoreAccount;
use App\Models\User;
use App\Traits\ReadsAppDevice;
use App\Traits\ThrottlesLogins;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    use ReadsAppDevice;
    use ThrottlesLogins;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            ...$this->deviceRules(),
        ];
    }

    /**
     * The account behind the credentials, under the same brake as the web
     * form: one per-minute net plus the wider nets for a botnet or a walker.
     *
     * @throws ValidationException
     */
    public function authenticatedUser(): User
    {
        $this->ensureIsNotRateLimited();

        $user = $this->userWithRightPassword();

        if ($user === null) {
            $this->recordFailedLogin();

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        $this->clearFailedLogins();

        return $user;
    }

    /**
     * A closed account inside its restore window comes back with the right
     * password, like on the web: coming back must not need a mail.
     */
    private function userWithRightPassword(): ?User
    {
        $email = (string) $this->string('email');
        $password = (string) $this->string('password');

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && Hash::check($password, $user->password)) {
            return $user;
        }

        $closed = User::restorableByEmail($email);

        if ($closed === null || ! Hash::check($password, $closed->password)) {
            return null;
        }

        return app(RestoreAccount::class)->handle($closed) ? $closed->fresh() : null;
    }
}
