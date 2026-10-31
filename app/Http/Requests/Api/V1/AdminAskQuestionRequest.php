<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\AdminAskDesk;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin's question from the phone: the same cap as the web's box, and the
 * stored conversation only by its id; `AskAdmin::resume` decides if it is hers.
 */
class AdminAskQuestionRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:'.AdminAskDesk::QUESTION_MAX],
            'conversation' => ['nullable', 'string', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['question' => __('ask_admin.field')];
    }

    public function question(): string
    {
        return trim((string) $this->validated('question'));
    }

    public function conversation(): ?string
    {
        $id = $this->validated('conversation');

        return is_string($id) ? $id : null;
    }

    protected function prepareForValidation(): void
    {
        // The web trims before checking: "   " is no question there either.
        $this->merge(['question' => is_string($this->input('question')) ? trim($this->input('question')) : $this->input('question')]);
    }
}
