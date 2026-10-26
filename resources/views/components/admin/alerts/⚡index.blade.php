<?php

use App\Classes\Main\PlatformBoard;
use App\Models\PlatformEvent;
use Illuminate\Contracts\View\View;
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
    #[Url(as: 'ver')]
    public string $filter = 'all';

    public int $limit = PlatformBoard::PAGE;

    /** The board is built once per request by the piece the mobile API reads too. */
    #[Computed]
    public function board(): PlatformBoard
    {
        return new PlatformBoard($this->filter, $this->limit);
    }

    public function loadMore(): void
    {
        $this->limit += PlatformBoard::PAGE;
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
    <div wire:poll.{{ PlatformBoard::POLL_SECONDS }}s.visible class="flex flex-col gap-4">
        @php($board = $this->board)

        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-subtle font-mono text-sm">{{ $board->readAt }}</span>
        </div>

        {{-- A board whose watcher stopped still paints green: say it first. --}}
        @if ($board->quiet !== null)
            <x-ui.alert variant="warning" icon="triangle-alert">
                {{ $board->quiet }}
            </x-ui.alert>
        @endif

        {{-- The verdict first: one sentence that says whether to keep reading. --}}
        @php($verdict = $board->verdict)
        <x-ui.alert
            :variant="$verdict['level'] === 'ok' ? 'success' : $verdict['level']"
            :icon="$verdict['level'] === 'ok' ? 'circle-check' : 'triangle-alert'"
            :title="$verdict['title']"
        >
            {{ $verdict['body'] }}
        </x-ui.alert>

        <div class="stat-grid stat-grid-fill">
            @foreach ($board->cards as $card)
                <x-ui.stat-card
                    :label="$card['label']"
                    :value="(string) $card['value']"
                    :icon="$card['icon']"
                    :tint="$card['tint']"
                    :href="route('admin.alerts', ['ver' => $card['key']])"
                    wire:key="card-{{ $card['key'] }}"
                >{{ $card['sub'] }}</x-ui.stat-card>
            @endforeach
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
                    @foreach ($board->filters as $option)
                        <x-ui.button
                            :variant="$filter === $option['value'] ? 'primary' : 'secondary'"
                            size="sm"
                            wire:click="$set('filter', '{{ $option['value'] }}')"
                            wire:key="filter-{{ $option['value'] }}"
                        >{{ $option['label'] }}</x-ui.button>
                    @endforeach
                </div>

                @php($rows = $board->rows)

                @if ($rows === [])
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
                                    @php($target = $event['target'])
                                    <tr wire:key="event-{{ $event['id'] }}" @class(['opacity-60' => $event['state'] === 'acknowledged'])>
                                        <td class="is-key" data-label="{{ __('platform_events.screen.col_severity') }}">
                                            <span @class(['status-tag', 'is-danger' => $event['severity']['tone'] === 'danger', 'is-warning' => $event['severity']['tone'] === 'warning'])>
                                                {{ $event['severity']['label'] }}
                                            </span>
                                        </td>
                                        <td data-label="{{ __('platform_events.screen.col_what') }}">
                                            {{-- One child: the stacked phone layout lays a cell's
                                            children side by side, one per line of text. --}}
                                            <div class="min-w-0">
                                            <div class="font-semibold">
                                                {{ $event['title'] }}
                                                @if ($event['times'] > 1)
                                                    <span class="text-subtle font-mono text-xs">× {{ $event['times'] }}</span>
                                                @endif
                                            </div>
                                            <div class="text-subtle text-xs">
                                                {{ $event['detail'] }}
                                            </div>
                                            {{-- Who and since when live under the line: two more columns
                                            pushed the actions out of the card at 1280px. --}}
                                            <div class="mt-1 flex flex-wrap items-center gap-x-2 text-xs">
                                                <span class="font-semibold">{{ $event['business'] }}</span>
                                                <span class="text-subtle font-mono whitespace-nowrap">{{ $event['since'] }}</span>
                                            </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                @if ($event['state'] === 'open')
                                                    @can('alerts.acknowledge')
                                                        <x-ui.button variant="secondary" size="sm" wire:click="acknowledge({{ $event['id'] }})">{{ __('platform_events.screen.ack') }}</x-ui.button>
                                                    @endcan
                                                @else
                                                    <span class="text-subtle text-xs">{{ $event['seen_by'] }}</span>
                                                @endif
                                                @if ($target !== null)
                                                    <x-ui.button variant="ghost" size="sm" :href="route($target['route'], $target['query'])" wire:navigate>{{ __('platform_events.screen.go') }}</x-ui.button>
                                                @endif
                                                <x-ask.admin-button :question="__('ask_admin.ask.alert', ['kind' => $event['title'], 'business' => $event['business']])" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-ui.load-more :shown="count($rows)" :total="$board->hasMore ? count($rows) + 1 : count($rows)" />
                @endif
            </x-ui.card>

            {{-- Reference layer: every component, worst first, each one opens its detail. --}}
            <x-ui.card class="p-5">
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <h2 class="font-display text-base font-bold">{{ __('platform_events.screen.sensors') }}</h2>
                    <span class="text-subtle text-xs">{{ __('platform_events.screen.sensors_sub') }}</span>
                </div>

                @forelse ($board->sensorGroups as $group)
                    <div class="mb-4" wire:key="group-{{ $group['key'] }}">
                        <p class="eyebrow mb-1 flex justify-between">
                            <span>{{ $group['title'] }}</span>
                            <span class="font-mono">{{ $group['ok'] }} / {{ $group['total'] }}</span>
                        </p>
                        @foreach ($group['sensors'] as $sensor)
                            @php($dot = ['ok' => 'var(--success)', 'warn' => 'var(--warning)', 'crit' => 'var(--danger)', 'unknown' => 'var(--text-subtle)'][$sensor['status']])
                            @php($goes = $sensor['target'])
                            <div x-data="{ open: false, copied: false }" data-diag="{{ $sensor['diagnostic'] }}" class="rounded-lg hover:bg-sunken" wire:key="sensor-{{ $sensor['key'] }}">
                                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="grid w-full grid-cols-[12px_minmax(0,1fr)_auto] items-center gap-3 px-1.5 py-2 text-left">
                                    <span class="size-2.5 rounded-full" style="background:{{ $dot }}"></span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold">{{ $sensor['name'] }}</span>
                                        <span class="text-subtle block text-xs">{{ $sensor['line'] }}</span>
                                    </span>
                                    <span class="font-mono text-sm font-bold">{{ $sensor['value'] }}</span>
                                </button>
                                <dl x-show="open" x-cloak class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 px-1.5 pb-3 pl-7 text-xs">
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_checked') }}</dt>
                                    <dd class="font-mono">{{ $sensor['checked'] }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_since') }}</dt>
                                    <dd class="font-mono">{{ $sensor['since'] }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_failures') }}</dt>
                                    <dd class="font-mono">{{ $sensor['failures'] }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_says') }}</dt>
                                    <dd>{{ $sensor['says'] }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.detail_fix') }}</dt>
                                    <dd>{{ $sensor['fix'] }}</dd>
                                    <dt class="text-subtle">{{ __('platform_events.screen.history') }}</dt>
                                    <dd>
                                        <div class="flex h-5 items-end gap-px" title="{{ __('platform_events.screen.history_hint') }}">
                                            @foreach ($sensor['history'] as $hour => $bucket)
                                                <span
                                                    class="h-full min-w-0 flex-1 rounded-sm"
                                                    style="background:{{ $bucket === null ? 'var(--border-default)' : ['ok' => 'var(--success)', 'warn' => 'var(--warning)', 'crit' => 'var(--danger)', 'unknown' => 'var(--text-subtle)'][$bucket] }}"
                                                    wire:key="hour-{{ $sensor['key'] }}-{{ $hour }}"
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
