<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\AskDesk;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The owner's question from the phone: the same cap as the web's box, and the
 * thread only as the ids of answers the server handed out, never as text.
 */
class AskQuestionRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:'.AskDesk::QUESTION_MAX],
            // Generous on purpose: a long session must never be refused; the server keeps only the last few.
            'thread' => ['sometimes', 'array', 'max:200'],
            'thread.*' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['question' => __('ask.field')];
    }

    public function question(): string
    {
        return trim((string) $this->validated('question'));
    }

    /** @return list<string> */
    public function thread(): array
    {
        return array_values(array_map('strval', (array) $this->validated('thread', [])));
    }

    protected function prepareForValidation(): void
    {
        // The web trims before checking: "   " is no question there either.
        $this->merge(['question' => is_string($this->input('question')) ? trim($this->input('question')) : $this->input('question')]);
    }
}
