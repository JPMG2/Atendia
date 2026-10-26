<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformSensorStatus;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\PlatformSensorReading;
use Illuminate\Support\Collection;

/**
 * The admin's glance board as data, with the words it prints: the web screen
 * and the mobile API both read from here, so the verdict, the counters and the
 * rows cannot say one thing on the panel and another on the phone.
 */
class PlatformBoard
{
    public const int PAGE = 25;

    /** How often a screen that shows this board refreshes itself: the web's poll and the app's are the same beat. */
    public const int POLL_SECONDS = 30;

    /** @var list<string> the filters the board offers, in order */
    public const array FILTERS = ['all', 'critical', 'important', 'info', 'unseen'];

    public function __construct(public readonly string $filter = 'all', public readonly int $limit = self::PAGE) {}

    /** @var array{critical: int, important: int, info: int, unseen: int} */
    public array $counts {
        get => $this->counts ??= PlatformEvent::counts();
    }

    /** @var list<array{key: string, label: string, value: int, sub: string, icon: string, tint: string}> */
    public array $cards {
        get => [
            [
                'key' => 'critical',
                'label' => __('platform_events.screen.critical'),
                'value' => $this->counts['critical'],
                'sub' => __('platform_events.screen.critical_sub'),
                'icon' => 'siren',
                'tint' => $this->counts['critical'] > 0 ? 'danger' : 'brand',
            ],
            [
                'key' => 'important',
                'label' => __('platform_events.screen.important'),
                'value' => $this->counts['important'],
                'sub' => __('platform_events.screen.important_sub', ['count' => $this->counts['unseen']]),
                'icon' => 'triangle-alert',
                'tint' => $this->counts['important'] > 0 ? 'warning' : 'brand',
            ],
            [
                'key' => 'info',
                'label' => __('platform_events.screen.info'),
                'value' => $this->counts['info'],
                'sub' => __('platform_events.screen.info_sub'),
                'icon' => 'info',
                'tint' => 'info',
            ],
        ];
    }

    /** @var list<array{value: string, label: string}> */
    public array $filters {
        get => array_map(fn (string $value): array => ['value' => $value, 'label' => match ($value) {
            'all' => __('platform_events.screen.filter_all'),
            'unseen' => __('platform_events.screen.filter_unseen'),
            default => __('platform_events.severity.'.$value),
        }], self::FILTERS);
    }

    /** @var string|null the warning when the watcher itself stopped: a board whose watcher is silent still paints green */
    public ?string $quiet {
        get => PlatformSensor::watcherIsQuiet()
            ? __('platform_events.screen.watcher_quiet', ['age' => PlatformSensor::lastLook()?->diffForHumans(null, true) ?? '—'])
            : null;
    }

    /** @var string "Leído a las 09:15:03": the moment every figure here was read */
    public string $readAt {
        get => __('platform_events.screen.read_at', ['time' => now()->format('H:i:s')]);
    }

    /**
     * The one sentence the board opens with: is the platform fine? It speaks of
     * the platform only; the business facts are the alarms right below.
     *
     * @var array{level: 'ok'|'warning'|'danger', title: string, body: string}
     */
    public array $verdict {
        get {
            $problems = PlatformSensor::problems();
            $level = match (true) {
                $problems->first()?->shownStatus() === PlatformSensorStatus::Crit => 'danger',
                $problems->isNotEmpty() => 'warning',
                default => 'ok',
            };

            if ($level === 'ok') {
                return [
                    'level' => 'ok',
                    'title' => __('platform_events.screen.verdict_ok'),
                    'body' => __('platform_events.screen.verdict_ok_body', ['total' => $this->groups->flatten(1)->count()]),
                ];
            }

            return [
                'level' => $level,
                'title' => trans_choice('platform_events.screen.verdict_bad', $problems->count()),
                'body' => __('platform_events.screen.verdict_bad_body', [
                    'names' => $problems->map(fn (PlatformSensor $sensor): string => __('platform_events.sensors.'.$sensor->sensor_key.'.name'))->implode(', '),
                ]),
            ];
        }
    }

    /** @var Collection<string, Collection<int, PlatformSensor>> the infrastructure sensors by group, the worst first */
    public Collection $groups {
        get => $this->groups ??= PlatformSensor::tree(PlatformSensor::INFRA);
    }

    /**
     * Every group of the reference layer with its sensors, ready to print.
     *
     * @var list<array{key: string, title: string, ok: int, total: int, sensors: list<array<string, mixed>>}>
     */
    public array $sensorGroups {
        get {
            $strips = PlatformSensorReading::strips($this->groups->flatten(1)->pluck('sensor_key')->all());

            return $this->groups->map(fn (Collection $sensors, string $group): array => [
                'key' => $group,
                'title' => __('platform_events.groups.'.$group),
                'ok' => $sensors->filter(fn (PlatformSensor $sensor): bool => $sensor->shownStatus() === PlatformSensorStatus::Ok)->count(),
                'total' => $sensors->count(),
                'sensors' => $sensors->map(fn (PlatformSensor $sensor): array => $this->sensorRow($sensor, $strips[$sensor->sensor_key] ?? []))->values()->all(),
            ])->values()->all();
        }
    }

    /**
     * One page of the open alarms, worst first. One row past the page is asked
     * for on purpose: it tells whether "load more" has anything to load.
     *
     * @var Collection<int, PlatformEvent>
     */
    public Collection $events {
        get => $this->events ??= PlatformEvent::board(
            $this->limit + 1,
            PlatformEventSeverity::tryFrom($this->filter),
            unseenOnly: $this->filter === 'unseen',
        );
    }

    /** @var list<array<string, mixed>> the page of alarms, ready to print */
    public array $rows {
        get => $this->events->take($this->limit)->map(fn (PlatformEvent $event): array => $this->eventRow($event))->values()->all();
    }

    /** @var bool whether there are alarms past this page */
    public bool $hasMore {
        get => $this->events->count() > $this->limit;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRow(PlatformEvent $event): array
    {
        $business = $event->aboutBusiness?->name ?? __('platform_events.screen.platform');
        $acknowledged = $event->state->value === 'acknowledged';

        return [
            'id' => $event->id,
            'severity' => ['key' => $event->severity->value, 'label' => $event->severity->label(), 'tone' => $event->severity->tone()],
            'title' => $event->kind->label(),
            'times' => $event->occurrences,
            'detail' => ($event->detail !== null && $event->detail !== '' ? $event->detail.' · ' : '').__('platform_events.kinds.'.$event->kind->value.'.hint'),
            'business' => $business,
            'since' => $event->first_seen_at->diffForHumans(null, true),
            'state' => $event->state->value,
            'seen_by' => $acknowledged ? __('platform_events.screen.seen_by', ['name' => $event->acknowledgedBy?->name ?? '—']) : null,
            'target' => $event->kind->target($event->about_business_id),
        ];
    }

    /**
     * @param  list<PlatformSensorStatus|null>  $history  the last day, hour by hour
     * @return array<string, mixed>
     */
    private function sensorRow(PlatformSensor $sensor, array $history): array
    {
        $shown = $sensor->shownStatus();

        return [
            'key' => $sensor->sensor_key,
            'name' => __('platform_events.sensors.'.$sensor->sensor_key.'.name'),
            'status' => $shown->value,
            'line' => $sensor->isStale() ? __('platform_events.screen.stale') : $sensor->detail,
            'value' => $sensor->isStale() ? '—' : ($sensor->value ?? ''),
            'checked' => $sensor->checked_at->format('d/m H:i:s').' · '.$sensor->checked_at->diffForHumans(null, true),
            'since' => ($sensor->changed_at?->format('d/m H:i') ?? '—').' · '.__('platform_events.screen.status_'.$shown->value),
            'failures' => $sensor->failures,
            'says' => $sensor->detail ?? '—',
            'fix' => __('platform_events.sensors.'.$sensor->sensor_key.'.fix'),
            'history' => array_map(fn (?PlatformSensorStatus $bucket): ?string => $bucket?->value, $history),
            'diagnostic' => $sensor->diagnostic(),
            'target' => $sensor->target(),
        ];
    }
}
