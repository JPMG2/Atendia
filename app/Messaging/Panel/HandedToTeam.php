<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;

/** The assistant just stepped aside: a person has to take this thread. */
class HandedToTeam extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::HandedToTeam;
    }

    public function dedupeKey(): string
    {
        return 'handoff:'.$this->model->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return ['name' => $this->model->contact_name ?? $this->model->contact_phone];
    }

    public function url(): ?string
    {
        return route('conversations', ['hilo' => $this->model->getKey()]);
    }
}
