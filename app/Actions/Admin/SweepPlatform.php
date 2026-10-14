<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventKind;
use App\Enums\PlatformSensorStatus;
use App\Jobs\PlatformHeartbeat;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\PlatformSensorReading;
use App\Services\PlatformSensors;

/**
 * One pass of the watcher: look at everything, write what it says, and tell
 * the feed about what CHANGED. It is level-triggered on purpose: it asks
 * "what is true now" every time instead of trusting that a transition was
 * seen, so a missed webhook or a restart cannot leave the board lying.
 */
class SweepPlatform
{
    public function __construct(
        private readonly PlatformSensors $sensors,
        private readonly RaisePlatformEvent $raise,
        private readonly ResolvePlatformEvent $resolve,
        private readonly ReconcilePlatformEvents $reconcile,
    ) {}

    /** @return array{looked: int, problems: int} */
    public function handle(): array
    {
        // Sent BEFORE reading: the next sweep finds out whether it ran.
        PlatformHeartbeat::dispatch();

        $threshold = max(1, (int) config('atendia.health.failures_to_raise'));
        $looked = 0;
        $problems = 0;

        foreach ($this->sensors->read() as $reading) {
            $sensor = PlatformSensor::query()->firstOrNew(['sensor_key' => $reading->key]);
            $changed = ! $sensor->exists || $sensor->status !== $reading->status;
            $failures = $reading->status->isProblem() ? ((int) $sensor->failures) + 1 : 0;

            $sensor->forceFill([
                'group' => $reading->group,
                'status' => $reading->status,
                'value' => $reading->value,
                'detail' => $reading->detail,
                'failures' => min($failures, 65_000),
                'checked_at' => now(),
                'changed_at' => $changed ? now() : $sensor->changed_at,
            ])->save();

            if (in_array($reading->group, PlatformSensor::INFRA, true)) {
                PlatformSensorReading::query()->create(['sensor_key' => $reading->key, 'status' => $reading->status, 'checked_at' => now()]);
            }

            $looked++;
            $problems += $reading->status->isProblem() ? 1 : 0;

            // Only what keeps the platform up reaches the feed from here: the
            // business-level sensors already have their own line when it happens.
            if (in_array($reading->group, ['system', 'external'], true)) {
                $this->tell($reading->key, $reading->status, $reading->detail, $failures, $threshold);
            }
        }

        // Rescued: the sensors are already written, and a reconcile failure
        // must not make the whole look read as if it never happened.
        rescue(fn () => $this->reconcile->handle());
        rescue(fn () => PlatformSensorReading::prune());

        return ['looked' => $looked, 'problems' => $problems];
    }

    private function tell(string $key, PlatformSensorStatus $status, ?string $detail, int $failures, int $threshold): void
    {
        $down = "sensor:{$key}:crit";
        $degraded = "sensor:{$key}:warn";

        // Only a clean look closes it: a thing that could not be read is not fixed.
        if ($status === PlatformSensorStatus::Ok) {
            $this->resolve->byKey($down);
            $this->resolve->byKey($degraded);

            return;
        }

        if (! $status->isProblem() || $failures < $threshold) {
            return;
        }

        [$kind, $mine, $other] = $status === PlatformSensorStatus::Crit
            ? [PlatformEventKind::SystemDown, $down, $degraded]
            : [PlatformEventKind::SystemDegraded, $degraded, $down];

        // The worse state replaces the milder one (and the other way round).
        $this->resolve->byKey($other);

        $text = __("platform_events.sensors.{$key}.name").' · '.($detail ?? '');
        $open = PlatformEvent::query()->active()->where('dedupe_key', $mine)->first();

        if ($open === null) {
            $this->raise->handle($kind, null, $text, $mine);

            return;
        }

        // A condition that goes on is not a new fact every two minutes: it
        // keeps its one line and only says it was seen again.
        $open->forceFill(['last_seen_at' => now(), 'detail' => mb_substr($text, 0, 255)])->save();
    }
}
