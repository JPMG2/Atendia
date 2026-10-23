<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The web's danger zone: the typed word plus the password, so closing is never a stray tap. */
class CloseAccountRequest extends FormRequest
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
            'confirmation' => ['required', 'string', Rule::in([__('settings.close.keyword')])],
            'current_password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['confirmation' => __('settings.close.confirmation_attribute')];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('confirmation'))) {
            $this->merge(['confirmation' => trim($this->input('confirmation'))]);
        }
    }

    public function confirmPassword(): void
    {
        $this->confirmCurrentPassword((string) $this->validated('current_password'));
    }
}
