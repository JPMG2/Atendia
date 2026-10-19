<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Admin\PublishMention;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;

/** The link of the post that mentions a business: without it the promise has no proof. */
class MentionPublishForm extends BaseForm
{
    public string $post_url = '';

    public function setup(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }

    public function save(int $mentionId): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($mentionId, $validated): NotificationDto {
            $published = app(PublishMention::class)->handle($mentionId, $validated['post_url']);

            if ($published === null) {
                return new NotificationDto(__('mentions.stale'), NotificationType::Error);
            }

            $this->reset();

            return new NotificationDto(__('mentions.published'), NotificationType::Success);
        }, __('mentions.failed'));
    }

    protected function transformServiceData(): array
    {
        return ['post_url' => trim($this->post_url)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['post_url' => ['required', 'url:https', 'max:255', AttributeValidator::xssFree()]];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['post_url' => __('mentions.fields.post_url')];
    }
}
