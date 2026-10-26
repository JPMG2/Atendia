<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\SupportDesk;
use App\Livewire\Forms\Client\SupportTicketForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A report to support, rule for rule as the web widget validates it. A box that does not hold
 * text is "nothing sent", never a crash.
 */
class SaveSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A NUL byte is no text to Postgres: it would fail the insert after the retries.
        $text = fn (string $key): ?string => is_string($this->input($key)) && trim(str_replace("\0", '', $this->input($key))) !== '' ? trim(str_replace("\0", '', $this->input($key))) : null;

        $this->replace(['body' => $text('body'), 'kind' => $text('kind'), 'screen' => $text('screen')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = SupportTicketForm::fieldRules();
        // The phone offers a closed list of screens: only one of them may be named.
        $rules['screen'][] = Rule::in(array_column((new SupportDesk)->screens, 'value'));

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return SupportTicketForm::fieldNames();
    }
}
