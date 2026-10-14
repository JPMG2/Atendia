<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\PlatformDigest;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\User;

/**
 * Closes the day on the admin's phone with what is still waiting. It stays
 * quiet when nothing is: a summary that says "all fine" every night teaches
 * her to stop reading the ones that matter.
 */
class SendPlatformDigest
{
    private const LINES = 5;

    /** @return bool Whether a summary left. */
    public function handle(): bool
    {
        $admins = User::role('admin')->whereNotNull('whatsapp')->get();
        $numbers = $admins->map(fn (User $user): string => $user->whatsappDigits())->filter()->unique()->values()->all();

        if ($numbers === []) {
            return false;
        }

        $events = PlatformEvent::query()
            ->with('aboutBusiness:id,name')
            ->whereIn('severity', [PlatformEventSeverity::Critical->value, PlatformEventSeverity::Important->value])
            ->where('state', PlatformEventState::Open->value)
            ->orderByRaw("CASE severity WHEN 'critical' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('last_seen_at')
            ->get();

        $platform = PlatformSensor::problems()
            ->map(fn (PlatformSensor $sensor): string => __('platform_events.sensors.'.$sensor->sensor_key.'.name'))
            ->implode(', ');

        if ($events->isEmpty() && $platform === '') {
            return false;
        }

        $lines = $events->take(self::LINES)->map(fn (PlatformEvent $event): string => $event->kind->label().($event->aboutBusiness === null ? '' : ' · '.$event->aboutBusiness->name))->all();

        return (new WhatsApp($admins->first(), $numbers, PlatformDigest::class, [
            [
                'critical' => $events->where('severity', PlatformEventSeverity::Critical)->count(),
                'important' => $events->where('severity', PlatformEventSeverity::Important)->count(),
            ],
            $lines,
            $platform,
            max(0, $events->count() - self::LINES),
        ]))->send();
    }
}
