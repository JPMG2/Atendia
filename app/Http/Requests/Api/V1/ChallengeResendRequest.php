<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Traits\ReadsAppDevice;
use Illuminate\Foundation\Http\FormRequest;

class ChallengeResendRequest extends FormRequest
{
    use ReadsAppDevice;

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
            'challenge_id' => ['required', 'string', 'size:48'],
            ...$this->deviceRules(),
        ];
    }
}
