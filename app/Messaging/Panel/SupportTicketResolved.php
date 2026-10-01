<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;

/** Her report is closed: the bell says so where she already looks. */
class SupportTicketResolved extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::SupportResolved;
    }

    public function dedupeKey(): string
    {
        return 'support:'.$this->model->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return ['code' => (string) $this->model->code];
    }
}
