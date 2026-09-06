<?php

declare(strict_types=1);

namespace App\Traits;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;

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

    /**
     * Dispatches only when there is news. On the wizard, Continuar is
     * navigation: toasting "nothing changed" (the only Info the service
     * emits) reads as "you did something wrong", so silence beats it.
     */
    public function dispatchChangeNotification(NotificationDto $notification): void
    {
        if ($notification->type !== NotificationType::Info) {
            $this->dispatchNotification($notification);
        }
    }
}
