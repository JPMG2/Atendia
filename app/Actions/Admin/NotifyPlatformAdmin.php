<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\PlatformAlert;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\User;

/**
 * Puts a critical fact on the admin's phone, once. Only critical interrupts:
 * everything else waits on the board.
 */
class NotifyPlatformAdmin
{
    /** @return int How many messages left. */
    public function handle(): int
    {
        $numbers = User::role('admin')
            ->whereNotNull('whatsapp')
            ->get()
            ->map(fn (User $user): string => $user->whatsappDigits())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($numbers === []) {
            return 0;
        }

        // Out of hours the alert waits (notified_at stays null) and leaves when
        // the window opens; the evening digest names whatever is still open.
        if (! Company::inSendWindow()) {
            return 0;
        }

        $sent = 0;

        $events = PlatformEvent::query()
            ->where('severity', PlatformEventSeverity::Critical->value)
            ->where('state', PlatformEventState::Open->value)
            ->whereNull('notified_at')
            ->with('aboutBusiness')
            ->get();

        foreach ($events as $event) {
            // Stamp first: a send that fails must not retry every two minutes,
            // and the same text is never sent twice.
            $event->forceFill(['notified_at' => now()])->save();

            rescue(fn () => (new WhatsApp($event, $numbers, PlatformAlert::class))->send());
            $sent++;
        }

        return $sent;
    }
}
