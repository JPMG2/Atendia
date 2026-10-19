<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use App\Models\BusinessMention;

/** The admin looked and the business does not follow the platform's account yet. */
class MentionNeedsFollow extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::MentionNeedsFollow;
    }

    public function dedupeKey(): string
    {
        return 'mention-follow:'.$this->model->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        /** @var BusinessMention $mention */
        $mention = $this->model;

        return ['accounts' => collect($mention->platform_accounts)->pluck('network')->join(', ', ' y ')];
    }

    public function url(): ?string
    {
        return route('dashboard');
    }
}
