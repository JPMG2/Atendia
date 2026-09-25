<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Livewire\Forms\BaseForm;

/** The owner's question to "Ask AtendIa": bounded, so one message cannot carry a novel to the model. */
class AskAtendiaForm extends BaseForm
{
    public string $question = '';

    public function validatedQuestion(): string
    {
        return $this->validateServiceData()['question'];
    }

    protected function transformServiceData(): array
    {
        return ['question' => trim($this->question)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['question' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['question' => __('ask.field')];
    }
}
