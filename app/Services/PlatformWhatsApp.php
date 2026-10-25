<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlatformSensorStatus;
use App\Enums\PlatformWhatsAppState;
use App\Models\PlatformSensor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The bridge instance that carries everything the platform itself says:
 * device codes, alerts to the admin, summaries. It belongs to Aliadito and to
 * no business — a business must never hold it, free it or delete it, because
 * the day that business leaves the platform would lose its own voice.
 */
final class PlatformWhatsApp
{
    /** Cache key of the last answer the bridge gave about the line, written by whoever asked it. */
    public const string READ_KEY = 'platform-whatsapp:read';

    /** The instance's name, as the environment names it (empty when none is configured). */
    public static function instance(): string
    {
        return (string) config('services.evolution.instance');
    }

    /** Whether this name is the platform's own instance. An empty name is nobody's. */
    public static function owns(?string $instance): bool
    {
        $platform = self::instance();

        return $platform !== '' && $instance === $platform;
    }

    /** Remembers what the bridge just said, so the topbar agrees with the screen that asked. */
    public static function remember(PlatformWhatsAppState $state, CarbonImmutable $at): void
    {
        Cache::put(self::READ_KEY, ['state' => $state->value, 'at' => $at->getTimestamp()], now()->addMinutes((int) config('atendia.health.stale_minutes')));
    }

    /**
     * The freshest honest word about the line without asking the bridge: a read
     * the screen just made, else the board's sensor. A reading nobody refreshed
     * is `Unreachable` (not verified), never green: the pill runs on every admin page.
     *
     * `since` is the board's own date for the current state, and only when the
     * board agrees with the state shown: no date is better than a wrong one.
     *
     * @return array{state: PlatformWhatsAppState, at: ?CarbonImmutable, since: ?CarbonImmutable}
     */
    public static function lastKnown(): array
    {
        $sensor = PlatformSensor::query()->where('sensor_key', 'platform_whatsapp')->first();
        $read = Cache::get(self::READ_KEY);

        if (is_array($read)) {
            $state = PlatformWhatsAppState::from($read['state']);

            return [
                'state' => $state,
                'at' => CarbonImmutable::createFromTimestamp($read['at']),
                'since' => self::sinceFor($sensor, $state),
            ];
        }

        if ($sensor === null) {
            return ['state' => PlatformWhatsAppState::Unreachable, 'at' => null, 'since' => null];
        }

        $state = match ($sensor->shownStatus()) {
            PlatformSensorStatus::Ok => PlatformWhatsAppState::Connected,
            PlatformSensorStatus::Crit => PlatformWhatsAppState::Closed,
            default => PlatformWhatsAppState::Unreachable,
        };

        return ['state' => $state, 'at' => CarbonImmutable::instance($sensor->checked_at), 'since' => self::sinceFor($sensor, $state)];
    }

    private static function sinceFor(?PlatformSensor $sensor, PlatformWhatsAppState $state): ?CarbonImmutable
    {
        if ($sensor === null || $sensor->changed_at === null || $state === PlatformWhatsAppState::Unreachable || $sensor->isStale()) {
            return null;
        }

        return ($sensor->status === PlatformSensorStatus::Ok) === $state->isConnected() ? CarbonImmutable::instance($sensor->changed_at) : null;
    }
}
