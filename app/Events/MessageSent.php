<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Prueba de vida del WebSocket.
 *
 * Canal PÚBLICO a propósito: alcanza para ver el circuito completo funcionando.
 * Cuando exista el aislamiento por negocio pasa a `PrivateChannel` más una
 * autorización en `routes/channels.php`; el resto no cambia.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $body) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('demo')];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
