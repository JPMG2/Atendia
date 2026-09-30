<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;

/**
 * The number stopped answering. The topbar pill says what is true NOW; this row
 * says WHEN it fell — the difference between "it is down" and "it was down all
 * night and nobody read those messages".
 */
class WhatsAppDisconnected extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::WhatsAppDisconnected;
    }

    /** One row per outage, not per check: keyed to the day it fell. */
    public function dedupeKey(): string
    {
        return 'whatsapp-down:'.now($this->model->localTimezone())->format('Y-m-d');
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return ['at' => now($this->model->localTimezone())->format('H:i')];
    }

    public function url(): ?string
    {
        return route('whatsapp');
    }
}
