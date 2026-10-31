<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Livewire\Forms\Admin\AskAdminDraftForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Her decision on the e-mail the assistant drafted, from the phone: the same
 * bounds as the web's card. The draft is named by its id and the conversation
 * by its own; `AdminAskDesk::decide` checks both are hers and still waiting.
 */
class AdminAskDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $sending = $this->boolean('send');

        return [
            'conversation' => ['required', 'string', 'uuid'],
            'draft' => ['required', 'string', 'max:200'],
            'send' => ['required', 'boolean'],
            // What she corrected only matters when it goes out.
            'subject' => $sending ? AskAdminDraftForm::fieldRules()['subject'] : ['nullable', 'string'],
            'message' => $sending ? AskAdminDraftForm::fieldRules()['message'] : ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return AskAdminDraftForm::fieldNames();
    }

    protected function prepareForValidation(): void
    {
        // The web trims before checking: a subject of spaces is no subject there either.
        $this->merge(collect(['subject', 'message'])
            ->filter(fn (string $key): bool => is_string($this->input($key)))
            ->mapWithKeys(fn (string $key): array => [$key => trim($this->input($key))])
            ->all());
    }
}
