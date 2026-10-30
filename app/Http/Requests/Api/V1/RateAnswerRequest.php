<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\AskFeedback;
use Illuminate\Foundation\Http\FormRequest;

/** One thumb on an answer the server handed out, named by the id it gave. */
class RateAnswerRequest extends FormRequest
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
            'id' => ['required', 'uuid'],
            'rating' => ['required', 'string', 'in:'.AskFeedback::UP.','.AskFeedback::DOWN],
        ];
    }
}
