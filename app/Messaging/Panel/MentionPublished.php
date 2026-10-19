<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use App\Models\BusinessMention;
use App\Models\MentionGoal;

/** The post is out: the bell says so and links straight to it. */
class MentionPublished extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::MentionPublished;
    }

    public function dedupeKey(): string
    {
        return 'mention-published:'.$this->model->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        /** @var BusinessMention $mention */
        $mention = $this->model;

        return ['achievement' => MentionGoal::achievement($mention->goal_threshold)];
    }

    public function url(): ?string
    {
        return $this->model->getAttribute('post_url');
    }
}
