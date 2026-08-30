<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Proof of life for the WebSocket, over a PRIVATE per-business channel.
 *
 * It only reaches whoever the callback in `routes/channels.php` authorises:
 * the users of that business, plus the owner.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $body, public int $businessId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('business.'.$this->businessId)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
