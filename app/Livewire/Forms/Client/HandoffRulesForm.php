<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Support\Facades\Auth;

/**
 * The owner's own derivation cases, one per line. They ALWAYS escalate,
 * whatever the general dial says: if the owner wrote it, it matters.
 */
class HandoffRulesForm extends BaseForm
{
    public string $rules = '';

    public function setup(): void
    {
        $this->rules = (string) Auth::user()?->business?->handoff_rules;

        $this->resetErrorBag();
    }

    public function save(): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($business, $validated): NotificationDto {

            $business->update(['handoff_rules' => $validated['rules']]);

            return new NotificationDto(__('client.assistant.handoff_saved'), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return [
            'rules' => trim($this->rules) === '' ? null : trim($this->rules),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'rules' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'rules' => __('client.assistant.handoff_rules_label'),
        ];
    }
}
