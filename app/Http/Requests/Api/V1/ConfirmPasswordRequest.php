<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Foundation\Http\FormRequest;

/** Re-confirms the owner before ending every other session, like the web's devices card. */
class ConfirmPasswordRequest extends FormRequest
{
    use ConfirmsCurrentPassword;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['current_password' => ['required', 'string']];
    }

    public function confirmPassword(): void
    {
        $this->confirmCurrentPassword((string) $this->validated('current_password'));
    }
}
