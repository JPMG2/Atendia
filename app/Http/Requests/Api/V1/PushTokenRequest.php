<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** The push address the app got from Expo for this install. */
class PushTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Only Expo's own shape: anything else could never be delivered.
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[A-Za-z0-9_-]{8,200}\]$/'],
        ];
    }
}
