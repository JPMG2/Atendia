<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * A booking whose reminder will not go out on its own: the person never wrote
 * to the business's WhatsApp (a number typed on the public link proves
 * nothing), or stopped answering. The owner can still call before the hour.
 */
class ReminderHeld extends PanelMessage
{
    /** @param  'cold'|'ignored'  $why */
    public function __construct(Model $model, private readonly string $why = 'cold')
    {
        parent::__construct($model);
    }

    public function type(): PanelNotificationType
    {
        return PanelNotificationType::ReminderHeld;
    }

    /** One row per booking: the reminder is retried every tick and must not repeat it. */
    public function dedupeKey(): string
    {
        return 'reminder-held:'.$this->model->id;
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
            'why' => $this->why,
        ];
    }

    /** Every tick re-reports the same held reminder: once read, it stays read. */
    public function revives(): bool
    {
        return false;
    }

    /** Lands on the agenda, where the booking is. */
    public function url(): ?string
    {
        return route('agenda');
    }
}
