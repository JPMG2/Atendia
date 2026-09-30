<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone on the team taught the assistant an answer. The owner is TOLD, not
 * asked: the answer is already live, because a queue waiting on the busiest
 * person is what stops the team from ever proposing one.
 */
class TaughtByTeammate extends PanelMessage
{
    public function __construct(Model $model, public string $who = '')
    {
        parent::__construct($model);
    }

    public function type(): PanelNotificationType
    {
        return PanelNotificationType::TaughtByTeammate;
    }

    /** Keyed to the answer: teaching the same one twice is one piece of news. */
    public function dedupeKey(): string
    {
        return 'taught:'.$this->model->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return [
            'who' => $this->who,
            'question' => (string) $this->model->title,
        ];
    }

    public function url(): ?string
    {
        return route('assistant');
    }
}
