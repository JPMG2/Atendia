<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use App\Models\PlatformEvent;

/**
 * A critical fact on the admin's phone: what it is, who it is about and the
 * door to act on it. Short enough to read on the lock screen.
 */
class PlatformAlert extends WhatsAppMessage
{
    public function text(): string
    {
        /** @var PlatformEvent $event */
        $event = $this->model;
        $business = $event->aboutBusiness?->name;

        return __('platform_events.whatsapp', [
            'kind' => $event->kind->label(),
            'detail' => $event->detail !== null && $event->detail !== '' ? $event->detail : __('platform_events.kinds.'.$event->kind->value.'.hint'),
            'business' => $business === null ? '' : "\n".__('platform_events.whatsapp_business', ['name' => $business]),
            'url' => route('admin.alerts'),
        ]);
    }
}
