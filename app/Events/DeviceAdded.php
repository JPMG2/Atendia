<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LoginDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WhatsApp-Web-style heads-up: the sessions ALREADY open elsewhere learn in
 * the moment that a new device joined the account, over the user's own
 * private channel — the mail alerts the inbox, this alerts the screen.
 */
class DeviceAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LoginDevice $device) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('security.user.'.$this->device->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'device.added';
    }

    /**
     * @return array{message: string, action: array{label: string, url: string}}
     */
    public function broadcastWith(): array
    {
        return [
            'message' => __('profile.devices.new_device_toast', ['label' => $this->device->label()]),
            'action' => [
                'label' => __('profile.devices.review_action'),
                'url' => route('settings.dispositivos'),
            ],
        ];
    }
}
