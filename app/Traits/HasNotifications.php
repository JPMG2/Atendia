<?php

declare(strict_types=1);

namespace App\Traits;

use App\Dto\NotificationDto;

trait HasNotifications
{
    public function dispatchNotification(NotificationDto $notification): void
    {
        $this->dispatch(
            'notify',
            type: $notification->type->value,
            message: $notification->message,
        );
    }
}
