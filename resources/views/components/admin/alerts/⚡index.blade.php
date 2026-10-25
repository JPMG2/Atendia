<?php

use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformSensorStatus;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\PlatformSensorReading;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The admin's glance board: what is watched and how it reads right now, and
 * the alarms that are open, worst first. Two layers on purpose — a sensor is
 * the present, an alarm is what somebody has to decide.
 */
new class extends Component
{
    private const PAGE = 25;

    #[Url(as: 'ver')]
    public string $filter = 'all';

    public int $limit = self::PAGE;

    /** @return array{critical: int, important: int, info: int, unseen: int} */
    #[Computed]
    public function counts(): array
    {
        return PlatformEvent::counts();
    }

    /** @return array{ok: int, total: int, problems: int} */
    #[Computed]
    public function summary(): array
    {
        return PlatformSensor::summary();
    }

    /**
     * The one-sentence answer the board opens with: is the platform fine?
     *
     * @return array{level: 'ok'|'warning'|'danger', names: string, count: int}
     */
    #[Computed]
    public function verdict(): array
    {
        // About the platform only: the business facts are the alarms and
        // tiles right below, and saying them twice is what made it noisy.
        $problems = PlatformSensor::problems();
        $worst = $problems->first()?->shownStatus();
        $level = match (true) {
            $worst === PlatformSensorStatus::Crit => 'danger',
            $problems->isNotEmpty() => 'warning',
            default => 'ok',
        };

        return [
            'level' => $level,
            'total' => $this->tree->flatten(1)->count(),
            'count' => $problems->count(),
            'names' => $problems->map(fn (PlatformSensor $s): string => __('platform_events.sensors.'.$s->sensor_key.'.name'))->implode(', '),
        ];
    }

    /** @return Collection<string, Collection<int, PlatformSensor>> */
    #[Computed]
    public function tree(): Collection
    {
        return PlatformSensor::tree(PlatformSensor::INFRA);
    }

    /**
     * The last day of every component, hour by hour, for its strip.
     *
     * @return array<string, list<PlatformSensorStatus|null>>
     */
    #[Computed]
    public function history(): array
    {
        return PlatformSensorReading::strips($this->tree->flatten(1)->pluck('sensor_key')->all());
    }

    /** @return Collection<int, PlatformEvent> */
    #[Computed]
    public function events(): Collection
    {
        $severity = PlatformEventSeverity::tryFrom($this->filter);

        // One row past the page: it tells whether "load more" has anything to load.
        return PlatformEvent::board($this->limit + 1, $severity, unseenOnly: $this->filter === 'unseen');
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    /** What the export menu carries to the report: the severity on screen. */
    #[Computed]
    public function exportParams(): array
    {
        return ['ver' => $this->filter === 'all' ? '' : $this->filter];
    }

    public function acknowledge(int $id): void
    {
        $this->authorize('alerts.acknowledge');

        PlatformEvent::acknowledgeById($id, auth()->user());
    }

    public function render(): View
    {
        return $this->view()->title(__('platform_events.screen.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('platform_events.screen.title')" :sub="__('platform_events.screen.sub')">
        <x-ui.export-menu report="avisos" :params="$this->exportParams" />
    </x-ui.page-head>

    @island(name: 'board')
    <div wire:poll.30s.visible class="flex flex-col gap-4">
        @php($quiet = PlatformSensor::watcherIsQuiet())
        @php($last = PlatformSensor::lastLook())

        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-subtle font-mono text-sm">{{ __('platform_events.screen.read_at', ['time' => now()->format('H:i:s')]) }}</span>
        </div>

        {{-- A board whose watcher stopped still paints green: say it first. --}}
        @if ($quiet)
            <x-ui.alert variant="warning" icon="triangle-alert">
                {{ __('platform_events.screen.watcher_quiet', ['age' => $last === null ? '—' : $last->diffForHumans(null, true)]) }}
            </x-ui.alert>
        @endif

        {{-- The verdict first: one sentence that says whether to keep reading. --}}
        @php($verdict = $this->verdict)
        @if ($verdict['level'] === 'ok')
            <x-ui.alert variant="success" icon="circle-check" :title="__('platform_events.screen.verdict_ok')">
                {{ __('platform_events.screen.verdict_ok_body', ['total' => $verdict['total']]) }}
            </x-ui.alert>
        @else
            <x-ui.alert :variant="$verdict['level']" icon="triangle-alert" :title="trans_choice('platform_events.screen.verdict_bad', $verdict['count'])">
                {{ __('platform_events.screen.verdict_bad_body', ['names' => $verdict['names']]) }}
            </x-ui.alert>
        @endif

        <div class="stat-grid stat-grid-fill">
            <x-ui.stat-card
                :label="__('platform_events.screen.critical')"
                :value="(string) $this->counts['critical']"
                icon="siren"
                :tint="$this->counts['critical'] > 0 ? 'danger' : 'brand'"
                :href="route('admin.alerts', ['ver' => 'critical'])"
            >{{ __('platform_events.screen.critical_sub') }}</x-ui.stat-card>

            <x-ui.stat-card
                :label="__('platform_events.screen.important')"
                :value="(string) $this->counts['important']"
                icon="triangle-alert"
                :tint="$this->counts['important'] > 0 ? 'warning' : 'brand'"
                :href="route('admin.alerts', ['ver' => 'important'])"
            >{{ __('platform_events.screen.important_sub', ['count' => $this->counts['unseen']]) }}</x-ui.stat-card>

            <x-ui.stat-card
                :label="__('platform_events.screen.info')"
                :value="(string) $this->counts['info']"
                icon="info"
                tint="info"
                :href="route('admin.alerts', ['ver' => 'info'])"
            >{{ __('platform_events.screen.info_sub') }}</x-ui.stat-card>

        </div>

        {{-- Side by side only when the alarms table has its 640px: below that the
        groups sit in a band above it and the table takes the full width. --}}
        <div class="grid items-start gap-4 2xl:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
            {{-- What somebody can act on comes first; the components are the reference. --}}
            <x-ui.card class="p-5">
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <h2 class="font-display text-base font-bold">{{ __('platform_events.screen.open') }}</h2>
                    <span class="text-subtle text-xs">{{ __('platform_events.screen.open_sub') }}</span>
                </div>

                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach (['all' => __('platform_events.screen.filter_all'), 'critical' => __('platform_events.severity.critical'), 'important' => __('platform_events.severity.important'), 'info' => __('platform_events.severity.info'), 'unseen' => __('platform_events.screen.filter_unseen')] as $value => $label)
                        <x-ui.button
                            :variant="$filter === $value ? 'primary' : 'secondary'"
                            size="sm"
                            wire:click="$set('filter', '{{ $value }}')"
                            wire:key="filter-{{ $value }}"
                        >{{ $label }}</x-ui.button>
                    @endforeach
                </div>

                @php($rows = $this->events->take($limit))

                @if ($rows->isEmpty())
                    <x-ui.empty-state compact icon="circle-check" :title="__('platform_events.screen.empty_title')" :body="__('platform_events.screen.empty_body')" />
                @else
                    <div class="pay-table-wrap">
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th>{{ __('platform_events.screen.col_severity') }}</th>
                                    <th>{{ __('platform_events.screen.col_what') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $event)
                                    @php($target = $event->kind->target($event->about_business_id))
                                    <tr wire:key="event-{{ $event->id }}" @class(['opacity-60' => $event->state->value === 'acknowledged'])>
                                        <td class="is-key" data-label="{{ __('platform_events.screen.col_severity') }}">
                                            <span @class(['status-tag', 'is-danger' => $event->severity->tone() === 'danger', 'is-warning' => $event->severity->tone() === 'warning', 'is-neutral' => $event->severity->tone() === 'info'])>
                                                {{ $event->severity->label() }}
                                            </span>
                                        </td>
                                        <td data-label="{{ __('platform_events.screen.col_what') }}">
                                            {{-- One child: the stacked phone layout lays a cell's
                                            children side by side, one per line of text. --}}
                                            <div class="min-w-0">
                                            <div class="font-semibold">
                                                {{ $event->kind->label() }}
                                                @if ($event->occurrences > 1)
                                                    <span class="text-subtle font-mono text-xs">× {{ $event->occurrences }}</span>
                                                @endif
                                            </div>
                                            <div class="text-subtle text-xs">
                                                {{ $event->detail !== null && $event->detail !== '' ? $event->detail.' · ' : '' }}{{ __('platform_events.kinds.'.$event->kind->value.'.hint') }}
                                            </div>
                                            {{-- Who and since when live under the line: two more columns
                                            pushed the actions out of the card at 1280px. --}}
                                            <div class="mt-1 flex flex-wrap items-center gap-x-2 text-xs">
                                                <span class="font-semibold">{{ $event->aboutBusiness?->name ?? __('platform_events.screen.platform') }}</span>
                                                <span class="text-subtle font-mono whitespace-nowrap">{{ $event->first_seen_at->diffForHumans(null, true) }}</span>
                                            </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                @if ($event->state->value === 'open')
                                                    @can('alerts.acknowledge')
                                                        <x-ui.button variant="secondary" size="sm" wire:click="acknowledge({{ $event->id }})">{{ __('platform_events.screen.ack') }}</x-ui.button>
                                                    @endcan
                                                @else
                                                    <span class="text-subtle text-xs">{{ __('platform_events.screen.seen_by', ['name' => $event->acknowledgedBy?->name ?? '—']) }}</span>
                                                @endif
                                                @if ($target !== null)
                                                    <x-ui.button variant="ghost" size="sm" :href="route($target['route'], $target['query'])" wire:navigate>{{ __('platform_events.screen.go') }}</x-ui.button>
                                                @endif
                                                <x-ask.admin-button :question="__('ask_admin.ask.alert', ['kind' => $event->kind->label(), 'business' => $event->aboutBusiness?->name ?? __('platform_events.screen.platform')])" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-ui.load-more :shown="$rows->count()" :total="$this->events->count()" />
                @endif
            </x-ui.card>

            {{-- Reference layer: every component, worst first, each one opens its detail. --}}
            <x-ui.card class="p-5">
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <h2 class="font-display text-base font-bold">{{ __('platform_events.screen.sensors') }}</h2>
                    <span class="text-subtle text-xs">{{ __('platform_events.screen.sensors_sub') }}</span>
                </div>

                @forelse ($this->tree as $group => $sensors)
                    <div class="mb-4" wire:key="group-{{ $group }}">
                        <p class="eyebrow mb-1 flex justify-between">
                            <span>{{ __('platform_events.groups.'.$group) }}</span>
                            <span class="font-mono">{{ $sensors->filter(fn ($s) => $s->shownStatus() === PlatformSensorStatus::Ok)->count() }} / {{ $sensors->count() }}</span>
                        </p>
                        @foreach ($sensors as $sensor)
                            @php($shown = $sensor->shownStatus())
                            @php($dot = ['ok' => 'var(--success)', 'warn' => 'var(--warning)', 'crit' => 'var(--danger)', 'unknown' => 'var(--text-subtle)'][$shown->value])
                            @php($goes = $sensor->target())
                            <div x-data="{ open: false, copied: false }" data-diag="{{ $sensor->diagnostic() }}" class="rounded-lg hover:bg-sunken" wire:key="sensor-{{ $sensor->sensor_key }}">
                                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="grid w-full grid-cols-[12px_minmax(0,1fr)_auto] items-center gap-3 px-1.5 py-2 text-left">
                                    <span class="size-2.5 rounded-full" style="background:{{ $dot }}"></span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold">{{ __('platform_events.sensors.'.$sensor->sensor_key.'.name') }}</span>
                                        <span class="text-subtle block text-xs">{{ $sensor->isStale() ? __('platform_events.screen.stale') : $sensor->detail }}</span>
                                    </span>
                                    <span class="font-mono text-sm font-bold">{{ $sensor->isStale() ? '—' : ($sensor->value ?? '') }}</span>
                                </button>
                                <dl x-show="open" x-cloak class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 px-1.5 pb-3 pl-7 text-xs">
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_checked') }}</dt>
                                    <dd class="font-mono">{{ $sensor->checked_at->format('d/m H:i:s') }} · {{ $sensor->checked_at->diffForHumans(null, true) }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_since') }}</dt>
                                    <dd class="font-mono">{{ $sensor->changed_at?->format('d/m H:i') ?? '—' }} · {{ __('platform_events.screen.status_'.$shown->value) }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_failures') }}</dt>
                                    <dd class="font-mono">{{ $sensor->failures }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_says') }}</dt>
                                    <dd>{{ $sensor->detail ?? '—' }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_fix') }}</dt>
                                    <dd>{{ __('platform_events.sensors.'.$sensor->sensor_key.'.fix') }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.history') }}</dt>
                                    <dd>
                                        <div class="flex h-5 items-end gap-px" title="{{ __('platform_events.screen.history_hint') }}">
                                            @foreach ($this->history[$sensor->sensor_key] ?? [] as $hour => $bucket)
                                                <span
                                                    class="h-full min-w-0 flex-1 rounded-sm"
                                                    style="background:{{ $bucket === null ? 'var(--border-default)' : ['ok' => 'var(--success)', 'warn' => 'var(--warning)', 'crit' => 'var(--danger)', 'unknown' => 'var(--text-subtle)'][$bucket->value] }}"
                                                    wire:key="hour-{{ $sensor->sensor_key }}-{{ $hour }}"
                                                ></span>
                                            @endforeach
                                        </div>
                                    </dd>
                                    <dd class="col-span-2 mt-1 flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" size="sm" icon="copy" x-on:click="navigator.clipboard?.writeText($el.closest('[data-diag]').dataset.diag); copied = true; setTimeout(() => copied = false, 2000)">
                                            <span x-text="copied ? @js(__('platform_events.screen.copied')) : @js(__('platform_events.screen.copy_diag'))"></span>
                                        </x-ui.button>
                                        @if ($goes !== null)
                                            <x-ui.button variant="ghost" size="sm" :href="route($goes['route'], $goes['query'])" wire:navigate>{{ __('platform_events.screen.detail_open') }}</x-ui.button>
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <x-ui.empty-state compact icon="siren" :title="__('platform_events.screen.sensors')" :body="__('platform_events.screen.no_sensors')" />
                @endforelse
            </x-ui.card>
        </div>
    </div>
    @endisland
</div>
