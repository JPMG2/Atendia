<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\PlatformAlert;
use App\Models\PlatformEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Puts a critical fact on the admin's phone, and tells it again if nobody
 * has looked. Only critical interrupts: everything else waits on the board.
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

        $sent = 0;
        $after = now()->subMinutes((int) config('atendia.health.escalate_after_minutes'));
        $max = (int) config('atendia.health.escalate_max');

        $events = PlatformEvent::query()
            ->where('severity', PlatformEventSeverity::Critical->value)
            ->where('state', PlatformEventState::Open->value)
            ->with('aboutBusiness')
            ->get();

        foreach ($events as $event) {
            $key = "platform.alert.sent.{$event->id}";
            $times = (int) Cache::get($key, 0);

            $due = $event->notified_at === null
                || ($event->notified_at->lt($after) && $times < $max);

            if (! $due) {
                continue;
            }

            // Stamp first: a send that fails must not retry every two minutes.
            $event->forceFill(['notified_at' => now()])->save();
            Cache::put($key, $times + 1, now()->addDays(2));

            rescue(fn () => (new WhatsApp($event, $numbers, PlatformAlert::class))->send());
            $sent++;
        }

        return $sent;
    }
}
