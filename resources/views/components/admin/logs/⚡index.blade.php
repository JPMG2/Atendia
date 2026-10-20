<?php

use App\Actions\Admin\IngestLogProblems;
use App\Dto\NotificationDto;
use App\Enums\LogProblemState;
use App\Enums\NotificationType;
use App\Models\LogProblem;
use App\Models\PlatformFigure;
use App\Services\Logs\LogReader;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What the logs say, as problems: one row per thing that keeps happening, with
 * the state somebody gave it. The lines themselves stay in the files; this
 * screen reads the summary a job keeps and shows its age.
 */
new class extends Component
{
    use HasNotifications;

    private const int PAGE = 25;

    #[Url(as: 'periodo', except: 'day')]
    public string $range = 'day';

    #[Url(as: 'estado', except: 'active')]
    public string $state = 'active';

    #[Url(as: 'nivel', except: '')]
    public string $level = '';

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    /** What a tile asked for: '' | new | worse. */
    #[Url(as: 'foco', except: '')]
    public string $focus = '';

    /** The problem in the side panel; in the URL, so a link lands on the same one. */
    #[Url(as: 'problema', except: null)]
    public ?int $open = null;

    public int $limit = self::PAGE;

    public function mount(): void
    {
        // The URL is the visitor's: anything it carries that this screen does not know falls back.
        $this->range = array_key_exists($this->range, LogProblem::RANGES) ? $this->range : 'day';
        $this->state = LogProblemState::tryFrom($this->state)?->value ?? 'active';
        $this->level = in_array($this->level, ['error', 'warning'], true) ? $this->level : '';
        $this->focus = in_array($this->focus, ['new', 'worse'], true) ? $this->focus : '';
    }

    public function setRange(string $range): void
    {
        if (array_key_exists($range, LogProblem::RANGES)) {
            $this->range = $range;
            $this->limit = self::PAGE;
        }
    }

    public function setState(string $state): void
    {
        if (LogProblemState::tryFrom($state) !== null) {
            $this->state = $state;
            $this->focus = '';
            $this->limit = self::PAGE;
        }
    }

    public function setLevel(string $level): void
    {
        $this->level = in_array($level, ['error', 'warning'], true) ? $level : '';
        $this->limit = self::PAGE;
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
    }

    public function clearFocus(): void
    {
        $this->focus = '';
        $this->limit = self::PAGE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    /** @return array{rows: \Illuminate\Database\Eloquent\Collection<int, LogProblem>, total: int} */
    #[Computed]
    public function board(): array
    {
        return LogProblem::board($this->range, LogProblemState::from($this->state), $this->level, $this->search, $this->focus, $this->limit);
    }

    /** @return array<int, list<int>> */
    #[Computed]
    public function sparks(): array
    {
        return LogProblem::sparks($this->board['rows']->pluck('id')->all());
    }

    /** @return array{active: int, resolved: int, muted: int} */
    #[Computed]
    public function tally(): array
    {
        return LogProblem::tally($this->range);
    }

    /** @return array{new: int, worse: int, open: int, last_error_at: ?\Carbon\CarbonInterface} */
    #[Computed]
    public function summary(): array
    {
        return LogProblem::summary($this->range);
    }

    /** The reading the job last left: null when it never ran. */
    #[Computed]
    public function figure(): ?PlatformFigure
    {
        return PlatformFigure::read(IngestLogProblems::FIGURE);
    }

    #[Computed]
    public function isStale(): bool
    {
        return $this->figure?->isOlderThan(IngestLogProblems::STALE_AFTER_MINUTES) ?? false;
    }

    #[Computed]
    public function problem(): ?LogProblem
    {
        return LogProblem::byId($this->open);
    }

    /** The newest example of the open problem, read from the file it lives in. */
    #[Computed]
    public function sample(): ?string
    {
        $problem = $this->problem;

        return $problem === null || $problem->last_file === null
            ? null
            : app(LogReader::class)->latestRaw($problem->last_file, $problem->fingerprint);
    }

    /** @return list<int> */
    #[Computed]
    public function trend(): array
    {
        return $this->problem === null ? [] : LogProblem::sparks([$this->problem->id])[$this->problem->id];
    }

    /** @return array{day: int, week: int, month: int} */
    #[Computed]
    public function totals(): array
    {
        return $this->problem?->totals() ?? ['day' => 0, 'week' => 0, 'month' => 0];
    }

    public function openProblem(int $id): void
    {
        $this->open = LogProblem::byId($id)?->id;
    }

    public function closeProblem(): void
    {
        $this->open = null;
    }

    public function resolve(int $id): void
    {
        $this->move($id, LogProblemState::Resolved);
    }

    public function mute(int $id): void
    {
        $this->move($id, LogProblemState::Muted);
    }

    public function reopen(int $id): void
    {
        $this->move($id, LogProblemState::Active);
    }

    private function move(int $id, LogProblemState $state): void
    {
        $this->authorize('logs.manage');

        $problem = LogProblem::byId($id);

        if ($problem === null) {
            return;
        }

        $problem->moveTo($state, auth()->user());
        $this->open = null;

        unset($this->board, $this->tally, $this->summary, $this->sparks, $this->problem);

        $this->dispatchNotification(new NotificationDto(__('logs.moved.'.$state->value), NotificationType::Success));
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('logs.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('logs.title')" :sub="__('logs.subtitle')">
        <div role="group" class="tabs" aria-label="{{ __('logs.title') }}">
            @foreach (array_keys(App\Models\LogProblem::RANGES) as $key)
                <button type="button" class="tab {{ $range === $key ? 'tab-active' : '' }}" wire:click="setRange('{{ $key }}')" aria-pressed="{{ $range === $key ? 'true' : 'false' }}" wire:key="range-{{ $key }}">
                    {{ __('logs.ranges.'.$key) }}
                </button>
            @endforeach
        </div>
    </x-ui.page-head>

    {{-- Always: the period and the filters live outside the island's own requests, and a skipped island would keep showing the old list. --}}
    @island(name: 'board', always: true)
        <div wire:poll.30s.visible>
            @if ($this->figure === null)
                <x-ui.empty-state framed icon="scroll-text" :title="__('logs.never_title')" :body="__('logs.never_body')" />
            @else
                @if ($this->isStale)
                    <x-ui.alert variant="warning" icon="clock" class="mb-3" data-testid="logs-stale">
                        {{ __('logs.stale', ['date' => $this->figure->computed_at->format('d/m/Y H:i')]) }}
                    </x-ui.alert>
                @endif

                <p class="lg-stamp font-mono" data-testid="logs-stamp">
                    {{ __('logs.read_at', ['date' => $this->figure->computed_at->format('d/m/Y H:i')]) }}
                </p>

                @php
                    $summary = $this->summary;
                    $period = __('logs.periods.'.$range);
                @endphp

                <div class="lg-tiles">
                    <x-ui.stat-card
                        :label="__('logs.tiles.new')"
                        :value="$summary['new']"
                        icon="sparkles"
                        tint="info"
                        :href="route('admin.logs', ['foco' => 'new'])"
                        data-testid="tile-new"
                    >{{ $summary['new'] > 0 ? __('logs.tiles.new_foot') : __('logs.tiles.new_none') }}</x-ui.stat-card>

                    <x-ui.stat-card
                        :label="__('logs.tiles.worse')"
                        :value="$summary['worse']"
                        icon="alert-triangle"
                        tint="danger"
                        :href="route('admin.logs', ['foco' => 'worse'])"
                        data-testid="tile-worse"
                    >{{ $summary['worse'] > 0 ? __('logs.tiles.worse_foot') : __('logs.tiles.worse_none') }}</x-ui.stat-card>

                    <x-ui.stat-card
                        :label="__('logs.tiles.open')"
                        :value="$summary['open']"
                        icon="scroll-text"
                        tint="brand"
                        :href="route('admin.logs', ['periodo' => $range])"
                        data-testid="tile-open"
                    >{{ __('logs.tiles.open_foot', ['period' => $period]) }}</x-ui.stat-card>

                    <x-ui.stat-card
                        :label="__('logs.tiles.last')"
                        :value="$summary['last_error_at']?->diffForHumans() ?? '—'"
                        icon="clock"
                        tint="warning"
                        :href="route('admin.logs')"
                        data-testid="tile-last"
                    >{{ $summary['last_error_at'] === null ? __('logs.tiles.last_none') : __('logs.tiles.last_foot') }}</x-ui.stat-card>
                </div>

                <x-ui.card class="lg-board" x-data="{ copied: false }">
                    <div class="lg-bar">
                        <div role="tablist" class="tabs">
                            @foreach (App\Enums\LogProblemState::cases() as $case)
                                <button type="button" role="tab" class="tab {{ $state === $case->value ? 'tab-active' : '' }}" wire:click="setState('{{ $case->value }}')" aria-selected="{{ $state === $case->value ? 'true' : 'false' }}" wire:key="state-{{ $case->value }}">
                                    <span>{{ __('logs.tabs.'.$case->value) }}</span>
                                    <span class="tab-badge">{{ $this->tally[$case->value] }}</span>
                                </button>
                            @endforeach
                        </div>

                        @if ($this->board['rows']->isNotEmpty())
                            <x-ui.button
                                variant="secondary"
                                size="sm"
                                icon="copy"
                                x-on:click="
                                    navigator.clipboard.writeText($refs.bundle.textContent).then(() => {
                                        copied = true;
                                        setTimeout(() => (copied = false), 1600);
                                    })
                                "
                            >
                                <span x-show="! copied">{{ __('logs.copy_summary') }}</span>
                                <span x-show="copied" x-cloak>{{ __('logs.copied') }}</span>
                            </x-ui.button>
                        @endif
                    </div>

                    {{-- Off screen and not rendered twice: the clipboard reads from here,
                    so what travels is exactly what the screen lists. --}}
                    <pre x-ref="bundle" class="sr-only">{{ app(App\Services\Logs\LogReader::class)->context() }}

@foreach ($this->board['rows'] as $row)
{{ __('logs.summary_line', ['title' => $row->title, 'count' => $row->window_count, 'last' => $row->last_seen_at->format('d/m/Y H:i')]) }}
@endforeach</pre>

                    <x-catalog.form-row>
                        <x-inputsform.input
                            span="text"
                            size="s"
                            type="search"
                            name="search"
                            icon="search"
                            data-key-focus="/"
                            :placeholder="__('logs.search_placeholder')"
                            :aria-label="__('logs.search')"
                            wire:model.live.debounce.400ms="search"
                        />

                        <div role="group" class="tabs lg-levels">
                            @foreach (['' => 'all', 'error' => 'error', 'warning' => 'warning'] as $value => $name)
                                <button type="button" class="tab {{ $level === $value ? 'tab-active' : '' }}" wire:click="setLevel('{{ $value }}')" aria-pressed="{{ $level === $value ? 'true' : 'false' }}" wire:key="level-{{ $name }}">
                                    {{ __('logs.levels.'.$name) }}
                                </button>
                            @endforeach
                        </div>

                        @if ($focus !== '')
                            <x-ui.button variant="secondary" size="sm" icon="x" wire:click="clearFocus">
                                {{ __('logs.tiles.'.$focus) }} · {{ __('logs.clear_filters') }}
                            </x-ui.button>
                        @endif
                    </x-catalog.form-row>

                    @if ($this->board['rows']->isEmpty())
                        <x-ui.empty-state
                            icon="check-circle"
                            :title="__('logs.empty.'.$state.'_title')"
                            :body="__('logs.empty.'.$state.'_body', ['period' => $period])"
                            :compact="true"
                        />
                    @else
                        <div class="pay-table-wrap">
                            <table class="pay-table lg-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('logs.columns.problem') }}</th>
                                        <th>{{ __('logs.columns.level') }}</th>
                                        <th class="is-num">{{ __('logs.columns.times') }}</th>
                                        <th>{{ __('logs.columns.trend') }}</th>
                                        <th>{{ __('logs.columns.last') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->board['rows'] as $row)
                                        @php($tag = $row->tag())
                                        <tr wire:key="problem-{{ $row->id }}" wire:click="openProblem({{ $row->id }})" @class(['lg-open' => $open === $row->id])>
                                            <td class="is-name is-key" data-label="{{ __('logs.columns.problem') }}">
                                                <span class="lg-title">
                                                    {{ $row->title }}
                                                    @if ($tag !== null)
                                                        <span @class(['status-tag', 'is-info' => $tag === 'new', 'is-danger' => $tag === 'worse', 'is-warning' => $tag === 'back'])>{{ __('logs.tags.'.$tag) }}</span>
                                                    @endif
                                                </span>
                                                @if ($row->origin() !== '')
                                                    <span class="aiu-note font-mono lg-origin">{{ $row->origin() }}</span>
                                                @endif
                                            </td>
                                            <td class="lg-fit" data-label="{{ __('logs.columns.level') }}">
                                                <span @class(['status-tag', 'is-danger' => $row->level === 'error', 'is-warning' => $row->level === 'warning'])>{{ __('logs.level_one.'.$row->level) }}</span>
                                            </td>
                                            <td class="is-num font-mono lg-fit" data-label="{{ __('logs.columns.times') }}">{{ $row->window_count }}</td>
                                            <td data-label="{{ __('logs.columns.trend') }}">
                                                <x-ui.sparkline :values="$this->sparks[$row->id] ?? []" :label="__('logs.columns.trend')" />
                                            </td>
                                            <td class="lg-fit" data-label="{{ __('logs.columns.last') }}">
                                                {{ $row->last_seen_at->diffForHumans() }}
                                                <span class="aiu-note font-mono lg-since">{{ __('logs.since', ['date' => $row->first_seen_at->format('d/m H:i')]) }}</span>
                                            </td>
                                            <td class="lg-fit" data-label="">
                                                @can('logs.manage')
                                                    <div class="lg-actions">
                                                        @if ($row->state === App\Enums\LogProblemState::Active)
                                                            {{-- No data-row-action: it would make a click on the row press "resolve" instead of opening the problem. --}}
                                                            <x-ui.button size="sm" variant="secondary" wire:click.stop="resolve({{ $row->id }})" data-testid="log-resolve">{{ __('logs.actions.resolve') }}</x-ui.button>
                                                            <x-ui.button size="sm" variant="ghost" wire:click.stop="mute({{ $row->id }})">{{ __('logs.actions.mute') }}</x-ui.button>
                                                        @else
                                                            <x-ui.button size="sm" variant="secondary" wire:click.stop="reopen({{ $row->id }})">
                                                                {{ $row->state === App\Enums\LogProblemState::Resolved ? __('logs.actions.reopen') : __('logs.actions.unmute') }}
                                                            </x-ui.button>
                                                        @endif
                                                    </div>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <x-ui.load-more :shown="$this->board['rows']->count()" :total="$this->board['total']" />

                        <p class="lg-note">
                            {{ trans_choice('logs.count', $this->board['total'], ['count' => $this->board['total'], 'period' => $period]) }}
                            · {{ __('logs.grouping_note') }}
                        </p>
                    @endif
                </x-ui.card>
            @endif
    {{-- Inside the island: a click on a row is a request FROM the island, and only the island is drawn back. --}}
    @if ($this->problem !== null)
        {{-- The x-data sits on the panel itself: the copy button lives in its footer, which is not inside the body. --}}
        <x-ui.slide-over
            x-data="{ copied: false }"
            x-on:slide-over-close="$wire.closeProblem()"
            :title="$this->problem->title"
            :subtitle="$this->problem->origin() ?: null"
        >
            <div class="lg-panel">
                <div class="flex flex-wrap gap-2">
                    <span @class(['status-tag', 'is-danger' => $this->problem->level === 'error', 'is-warning' => $this->problem->level === 'warning'])>{{ __('logs.level_one.'.$this->problem->level) }}</span>
                    <span class="status-tag is-neutral">{{ __('logs.panel.state.'.$this->problem->state->value) }}</span>
                </div>

                <div class="lg-stats">
                    <div><small>{{ __('logs.panel.last_day') }}</small><b class="font-mono">{{ $this->totals['day'] }}</b></div>
                    <div><small>{{ __('logs.panel.last_week') }}</small><b class="font-mono">{{ $this->totals['week'] }}</b></div>
                    <div><small>{{ __('logs.panel.last_month') }}</small><b class="font-mono">{{ $this->totals['month'] }}</b></div>
                </div>

                <div>
                    <h3 class="lg-h3">{{ __('logs.panel.chart') }}</h3>
                    @php($peak = max([1, ...$this->trend]))
                    <div class="lg-bars" role="img" aria-label="{{ __('logs.panel.chart') }}">
                        @foreach ($this->trend as $count)
                            <i style="height:{{ $count > 0 ? max(8, intval(100 * $count / $peak)) : 3 }}%" @class(['is-empty' => $count === 0])></i>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="lg-h3">{{ __('logs.panel.when') }}</h3>
                    <p class="lg-note">
                        {{ __('logs.panel.when_text', ['first' => $this->problem->first_seen_at->format('d/m/Y H:i'), 'last' => $this->problem->last_seen_at->format('d/m/Y H:i')]) }}
                        @if ($this->problem->regressed_at !== null)
                            {{ __('logs.panel.back_note') }}
                        @endif
                    </p>
                </div>

                <div>
                    <h3 class="lg-h3">{{ __('logs.panel.message') }}</h3>
                    <pre class="lg-pre font-mono">{{ $this->problem->title }}</pre>
                </div>

                <div>
                    <h3 class="lg-h3">{{ __('logs.panel.sample') }}</h3>
                    @if ($this->sample !== null)
                        <pre class="lg-pre font-mono" x-ref="sample">{{ $this->sample }}</pre>
                    @else
                        <p class="lg-note">{{ __('logs.panel.sample_gone') }}</p>
                    @endif
                </div>

                <x-slot:footer>
                    @can('logs.manage')
                        @if ($this->problem->state === App\Enums\LogProblemState::Active)
                            <x-ui.button variant="primary" wire:click="resolve({{ $this->problem->id }})">{{ __('logs.actions.resolve') }}</x-ui.button>
                            <x-ui.button variant="secondary" wire:click="mute({{ $this->problem->id }})">{{ __('logs.actions.mute') }}</x-ui.button>
                        @else
                            <x-ui.button variant="primary" wire:click="reopen({{ $this->problem->id }})">
                                {{ $this->problem->state === App\Enums\LogProblemState::Resolved ? __('logs.actions.reopen') : __('logs.actions.unmute') }}
                            </x-ui.button>
                        @endif
                    @endcan

                    <x-ui.button
                        variant="secondary"
                        icon="copy"
                        data-testid="log-copy"
                        x-on:click="
                            navigator.clipboard.writeText({{ Js::from(app(App\Services\Logs\LogReader::class)->context((string) $this->problem->last_file)) }} + '\n\n' + ($refs.sample ? $refs.sample.textContent : {{ Js::from($this->problem->title) }})).then(() => {
                                copied = true;
                                setTimeout(() => (copied = false), 1600);
                            })
                        "
                    >
                        <span x-show="! copied">{{ __('logs.copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('logs.copied') }}</span>
                    </x-ui.button>
                </x-slot:footer>
            </div>
        </x-ui.slide-over>
    @endif
        </div>
    @endisland
</div>
