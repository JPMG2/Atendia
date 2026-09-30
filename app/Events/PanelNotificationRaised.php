<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A row landed in the bell's inbox. It carries no content on purpose: the
 * socket only says "look again", and the panel re-reads under the tenant scope
 * of whoever is watching — a payload on the wire would be data leaving the
 * fence for nothing.
 */
class PanelNotificationRaised implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $businessId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('business.'.$this->businessId)];
    }

    public function broadcastAs(): string
    {
        return 'panel.notified';
    }
}
