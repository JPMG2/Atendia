<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformSensorStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One thing the platform watches and what it said at the last look. The
 * platform's own, like its feed: only the admin panel reads it.
 */
#[Fillable(['sensor_key', 'group', 'status', 'value', 'detail', 'failures', 'checked_at', 'changed_at'])]
class PlatformSensor extends Model
{
    /** The order the board shows its groups in: what keeps it up, then who, then money. */
    public const array GROUPS = ['system', 'customers', 'money', 'compliance', 'external'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => PlatformSensorStatus::class,
            'failures' => 'integer',
            'checked_at' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }

    /** What keeps the platform itself up: the part a technician owns. The rest is business facts the alarms already carry. */
    public const array INFRA = ['system', 'external'];

    /**
     * Every sensor in its group, groups in board order, the worst first
     * inside each so the eye lands on the problem and not on the list.
     *
     * @param  list<string>  $groups
     * @return Collection<string, Collection<int, self>>
     */
    public static function tree(array $groups = self::GROUPS): Collection
    {
        $all = self::query()->orderBy('id')->get();

        return collect($groups)
            ->mapWithKeys(fn (string $group): array => [
                $group => $all->where('group', $group)
                    ->sortByDesc(fn (self $sensor): int => $sensor->shownStatus()->weight())
                    ->values(),
            ])
            ->filter(fn (Collection $sensors): bool => $sensors->isNotEmpty());
    }

    /**
     * What the tile paints: a reading nobody has refreshed is not green, it
     * is unknown. An old green is the most dangerous colour a board has.
     */
    public function shownStatus(): PlatformSensorStatus
    {
        return $this->isStale() ? PlatformSensorStatus::Unknown : $this->status;
    }

    public function isStale(): bool
    {
        return $this->checked_at->lt(now()->subMinutes((int) config('atendia.health.stale_minutes')));
    }

    /**
     * How many are green out of how many are watched, for the counter.
     *
     * @return array{ok: int, total: int, problems: int}
     */
    public static function summary(): array
    {
        $all = self::query()->get();

        return [
            'ok' => $all->filter(fn (self $sensor): bool => $sensor->shownStatus() === PlatformSensorStatus::Ok)->count(),
            'total' => $all->count(),
            'problems' => $all->filter(fn (self $sensor): bool => $sensor->shownStatus()->isProblem())->count(),
        ];
    }

    /**
     * The screen that lets someone act on this sensor, or null when the
     * thing is infrastructure with no screen of its own.
     *
     * @return array{route: string, query: array<string, string>}|null
     */
    public function target(): ?array
    {
        $route = match ($this->sensor_key) {
            'queue', 'database', 'redis', 'disk' => 'admin.logs',
            'ai' => 'admin.ai-usage',
            'platform_whatsapp' => 'admin.integrations',
            'whatsapp_links', 'signups' => 'admin.businesses',
            'receipts' => 'admin.payments',
            'late', 'paused' => 'admin.collections',
            'suspended', 'appeals', 'content' => 'admin.compliance',
            default => null,
        };

        return $route === null ? null : ['route' => $route, 'query' => []];
    }

    /**
     * Plain text a technician can paste into a ticket or a chat: what it
     * is, what it said, since when, and where it came from.
     */
    public function diagnostic(): string
    {
        return implode("\n", [
            __('platform_events.diag.title', ['name' => __('platform_events.sensors.'.$this->sensor_key.'.name')]),
            __('platform_events.diag.status', ['status' => __('platform_events.screen.status_'.$this->shownStatus()->value), 'value' => $this->value ?? '—']),
            __('platform_events.diag.says', ['detail' => $this->detail ?? '—']),
            __('platform_events.diag.checked', ['at' => $this->checked_at->format('d/m/Y H:i:s')]),
            __('platform_events.diag.since', ['at' => $this->changed_at?->format('d/m/Y H:i') ?? '—', 'failures' => $this->failures]),
            __('platform_events.diag.key', ['key' => $this->sensor_key, 'app' => config('app.url')]),
        ]);
    }

    /**
     * The sensors that are not green right now, worst first: what the
     * verdict names so nobody has to scan 17 rows to find the problem.
     *
     * @return Collection<int, self>
     */
    public static function problems(): Collection
    {
        return self::query()->whereIn('group', self::INFRA)->orderBy('id')->get()
            ->filter(fn (self $sensor): bool => $sensor->shownStatus() !== PlatformSensorStatus::Ok)
            ->sortByDesc(fn (self $sensor): int => $sensor->shownStatus()->weight())
            ->values();
    }

    /** When the sweep last looked at anything: the board's own pulse. */
    public static function lastLook(): ?Carbon
    {
        $at = self::query()->max('checked_at');

        return $at === null ? null : Carbon::parse($at);
    }

    /** Whether the watcher itself has gone quiet: no sensor was looked at lately. */
    public static function watcherIsQuiet(): bool
    {
        $at = self::lastLook();

        return $at === null || $at->lt(now()->subMinutes((int) config('atendia.health.stale_minutes')));
    }
}
