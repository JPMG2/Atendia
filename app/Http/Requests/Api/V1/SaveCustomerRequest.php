<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Client;
use App\Livewire\Forms\Client\CustomerForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The customer fiche, rule for rule as the web's sheet validates it. A PUT is
 * partial: a box the phone leaves out stays as stored, and one sent empty
 * clears it, exactly as the sheet does.
 */
class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $directory = Client::for($this->user())->directory;
        $customer = $directory?->customer((int) $this->route('customer'));

        abort_if($customer === null, 404);

        $stored = [
            'name' => (string) $customer->name,
            'email' => (string) $customer->email,
            'birthday' => (string) $customer->birthday?->toDateString(),
            'notes' => (string) $customer->notes,
        ];

        // A value that is not text (an array, a number) is read as nothing sent for that box.
        $sent = collect($stored)->keys()
            ->filter(fn (string $box): bool => $this->has($box) && (is_string($this->input($box)) || $this->input($box) === null))
            ->mapWithKeys(fn (string $box): array => [$box => (string) $this->input($box)])
            ->all();

        $this->replace(CustomerForm::payload([...$stored, ...$sent]));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return CustomerForm::fieldRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CustomerForm::fieldNames();
    }
}
