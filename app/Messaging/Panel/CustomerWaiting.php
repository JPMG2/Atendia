<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use Illuminate\Database\Eloquent\Model;

/** A thread handed to the team and still unanswered past the grace window. */
class CustomerWaiting extends PanelMessage
{
    public function __construct(Model $model, public int $minutes = 0)
    {
        parent::__construct($model);
    }

    public function type(): PanelNotificationType
    {
        return PanelNotificationType::CustomerWaiting;
    }

    /**
     * Keyed to THIS escalation: waiting longer is the same fact, but a customer
     * escalated again next week is a new one and deserves its own row.
     */
    public function dedupeKey(): string
    {
        return 'waiting:'.$this->model->getKey().':'.$this->model->escalated_at?->timestamp;
    }

    /** The sweep re-reports it every few minutes: reviving would make it unreadable. */
    public function revives(): bool
    {
        return false;
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return [
            'name' => $this->model->contact_name ?? $this->model->contact_phone,
            'minutes' => $this->minutes,
        ];
    }

    public function url(): ?string
    {
        return route('conversations', ['hilo' => $this->model->getKey()]);
    }
}
