<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Admin\RecordFollowerCount;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\User;
use App\Rules\AttributeValidator;

/** What an account of the platform has today, typed by whoever looked at it. */
class FollowerCountForm extends BaseForm
{
    public string $followers = '';

    public function setup(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }

    public function save(int $socialLinkId, User $by): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($socialLinkId, $validated, $by): NotificationDto {
            $saved = app(RecordFollowerCount::class)->handle($socialLinkId, $validated['followers'], $by);

            if ($saved === null) {
                return new NotificationDto(__('mentions.stale'), NotificationType::Error);
            }

            $this->reset();

            return new NotificationDto(__('mentions.followers.saved'), NotificationType::Success);
        }, __('mentions.failed'));
    }

    protected function transformServiceData(): array
    {
        $typed = trim($this->followers);

        return ['followers' => ctype_digit($typed) ? (int) $typed : $typed];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['followers' => [...AttributeValidator::numericInteger(true, 0), 'max:1000000000']];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['followers' => __('mentions.followers.field')];
    }
}
