<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Moderation;

use App\Actions\Moderation\SubmitSuspensionAppeal;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;

/** Why a suspended business thinks the filter got it wrong: one message, read by the admin. */
class AppealForm extends BaseForm
{
    public string $message = '';

    public function setup(): void
    {
        $this->reset('message');
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($business, $validated): NotificationDto {
            $sent = app(SubmitSuspensionAppeal::class)->handle($business, $validated['message']);
            $this->setup();

            return $sent
                ? new NotificationDto(__('moderation.appeal.sent'), NotificationType::Success)
                : new NotificationDto(__('moderation.appeal.already'), NotificationType::Warning);
        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return ['message' => trim($this->message)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['message' => ['required', 'string', 'min:10', 'max:1000', AttributeValidator::xssFree()]];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['message' => __('moderation.appeal.field')];
    }
}
