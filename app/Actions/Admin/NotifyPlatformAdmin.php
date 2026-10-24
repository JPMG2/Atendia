<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use App\Mail\PlatformAlertMail;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\PlatformAlert;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\User;

/**
 * Puts a critical fact on the admin's phone, once, and in her inbox, once. Only
 * critical interrupts: everything else waits on the board. The e-mail does not
 * wait for the send window nor depend on the WhatsApp line: that line can be
 * the very thing that is down.
 */
class NotifyPlatformAdmin
{
    /** @return int How many WhatsApp messages left. */
    public function handle(): int
    {
        $this->email();

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

    /** Every open critical reaches the admins' inbox once, at any hour. */
    private function email(): void
    {
        $addresses = User::role('admin')->whereNotNull('email')->pluck('email')->unique()->values()->all();

        if ($addresses === []) {
            return;
        }

        $events = PlatformEvent::query()
            ->where('severity', PlatformEventSeverity::Critical->value)
            ->where('state', PlatformEventState::Open->value)
            ->whereNull('emailed_at')
            ->with('aboutBusiness')
            ->get();

        foreach ($events as $event) {
            // Stamp first, like the phone: a failing send must not retry every two minutes.
            $event->forceFill(['emailed_at' => now()])->save();

            rescue(fn () => (new Email($event, $addresses, PlatformAlertMail::class))->send());
        }
    }
}
