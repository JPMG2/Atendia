<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** The web's PasswordForm, rule for rule: the same policy, the same throttled re-check. */
class ChangePasswordRequest extends FormRequest
{
    use ConfirmsCurrentPassword;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
            'logout_others' => ['sometimes', 'boolean'],
        ];
    }

    /** Throws the field error the web shows when the current password is wrong or the tries ran out. */
    public function confirmPassword(): void
    {
        $this->confirmCurrentPassword((string) $this->validated('current_password'));
    }

    /** Ending the other sessions is the default, like the web's switch. */
    public function logoutOthers(): bool
    {
        return $this->boolean('logout_others', true);
    }
}
