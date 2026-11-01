<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;

/**
 * A closure notice that could not go out once its turn came (the line dropped,
 * the day's cap was spent, the customer was blocked or the bridge failed). The
 * hour still stands and the owner was told it would be cancelled: without this
 * row the customer would arrive at a closed door and nobody would know.
 */
class ClosureNoticeHeld extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::ClosureNoticeHeld;
    }

    /** One row per booking: a notice retried later never doubles it. */
    public function dedupeKey(): string
    {
        return 'closure-held:'.$this->model->id;
    }

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        $business = $this->model->business;

        return [
            'name' => (string) ($this->model->customer?->name ?: $this->model->customer?->phone),
            'when' => $this->model->starts_at->setTimezone($business->localTimezone())->format('d/m/Y H:i'),
        ];
    }

    /** Lands on the agenda, where the booking still stands. */
    public function url(): ?string
    {
        return route('agenda');
    }
}
