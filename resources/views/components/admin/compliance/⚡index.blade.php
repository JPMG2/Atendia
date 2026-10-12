<?php

use App\Enums\ComplianceEventKind;
use App\Models\Business;
use App\Models\ComplianceEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The file of each business: what happened to it and what was done about it.
 *
 * Not a list of reprimands. A suspension that was lifted, a payment that was
 * regularized, an appeal she answered: the answer sits in the same file as
 * the fault, because that is what a decision about this business needs.
 */
new class extends Component
{
    /** The open file, in the URL, so a tile or a mail can land on one business. */
    #[Url(as: 'negocio')]
    public ?int $open = null;

    #[Url(as: 'estado')]
    public string $filter = '';

    #[Url(as: 'buscar')]
    public string $search = '';

    private const int PAGE = 25;

    public int $visible = self::PAGE;

    public function loadMore(): void
    {
        $this->visible += self::PAGE;
    }

    public function updatedSearch(): void
    {
        $this->visible = self::PAGE;
    }

    public function updatedFilter(): void
    {
        $this->visible = self::PAGE;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filter');
        $this->visible = self::PAGE;
    }

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    public function close(): void
    {
        $this->open = null;
    }

    /**
     * @return Collection<int, array{id: int, name: string, standing: string, recent: int, total: int, last_at: \Illuminate\Support\Carbon|null, last_kind: ComplianceEventKind|null}>
     */
    #[Computed]
    public function board(): Collection
    {
        return ComplianceEvent::board($this->filter, $this->search, $this->visible);
    }

    #[Computed]
    public function matching(): int
    {
        return ComplianceEvent::boardCount($this->filter, $this->search);
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function filterOptions(): array
    {
        return collect([ComplianceEvent::SUSPENDED, ComplianceEvent::DEBT, 'recent'])
            ->map(fn (string $value): array => ['value' => $value, 'label' => __('compliance.filters.'.$value)])
            ->all();
    }

    #[Computed]
    public function business(): ?Business
    {
        return $this->open === null ? null : ComplianceEvent::businessOf($this->open);
    }

    /** @return Collection<int, ComplianceEvent> */
    #[Computed]
    public function timeline(): Collection
    {
        return $this->open === null ? new Collection : ComplianceEvent::timelineOf($this->open);
    }

    /** What a business is today, said in its own word. */
    #[Computed]
    public function standing(): string
    {
        return $this->business === null
            ? ComplianceEvent::CLEAR
            : ComplianceEvent::standingOf(
                $this->business,
                $this->timeline->where('occurred_at', '>=', now()->subDays(ComplianceEvent::RECENT_DAYS))->count(),
            );
    }

    public function render(): View
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('compliance.title'));
    }
};
?>

<div class="bp-main">
    <x-ui.page-head :title="__('compliance.title')" :sub="__('compliance.sub')">
        <x-slot:inline>
            <span class="status-tag">{{ trans_choice('compliance.count', $this->matching, ['count' => $this->matching]) }}</span>
        </x-slot:inline>
        {{-- The file is live, but it says when it was read. --}}
        <span class="sup-age">{{ __('compliance.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]) }}</span>
    </x-ui.page-head>

    <x-ui.card class="bp-card">
        <x-catalog.form-row>
            <x-inputsform.input
                size="s"
                span="text"
                :label="__('compliance.search')"
                name="search"
                icon="search"
                :placeholder="__('compliance.search_placeholder')"
                wire:model.live.debounce.400ms="search"
            />

            <x-inputsform.combobox
                size="s"
                span="short"
                :label="__('compliance.filter')"
                name="filter"
                :options="$this->filterOptions"
                :value="$filter"
                :placeholder="__('compliance.filters.all')"
                wire:model.live="filter"
            />

            @if ($search !== '' || $filter !== '')
                <x-ui.button variant="ghost" size="sm" wire:click="clearFilters">
                    {{ __('admin.keys.clear_filters') }}
                </x-ui.button>
            @endif
        </x-catalog.form-row>

        @if ($this->board->isEmpty())
            {{-- Nothing found is not the same sentence as nothing happened. --}}
            @if ($search === '' && $filter === '')
                <x-ui.empty-state
                    compact
                    icon="shield-check"
                    :title="__('compliance.empty_title')"
                    :body="__('compliance.empty_body')"
                />
            @else
                <p class="text-muted text-sm">{{ __('compliance.no_match') }}</p>
            @endif
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('compliance.table.business') }}</th>
                            <th>{{ __('compliance.table.standing') }}</th>
                            <th class="is-num">{{ __('compliance.table.recent', ['days' => ComplianceEvent::RECENT_DAYS]) }}</th>
                            <th class="is-num">{{ __('compliance.table.total') }}</th>
                            <th>{{ __('compliance.table.last') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->board as $row)
                            <tr wire:key="compliance-{{ $row['id'] }}" @class(['is-selected' => $this->open === $row['id']])>
                                <td class="is-name is-key" data-label="{{ __('compliance.table.business') }}">
                                    <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $row['id']]) }}">{{ $row['name'] }}</a>
                                </td>
                                <td data-label="{{ __('compliance.table.standing') }}">
                                    <span @class([
                                        'status-tag',
                                        'is-danger' => in_array($row['standing'], [ComplianceEvent::SUSPENDED, ComplianceEvent::DEBT], true),
                                        'is-warning' => $row['standing'] === ComplianceEvent::OBSERVED,
                                        'is-success' => $row['standing'] === ComplianceEvent::CLEAR,
                                    ])>{{ __('compliance.standing.'.$row['standing']) }}</span>
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('compliance.table.recent', ['days' => ComplianceEvent::RECENT_DAYS]) }}">{{ $row['recent'] }}</td>
                                <td class="is-num font-mono" data-label="{{ __('compliance.table.total') }}">{{ $row['total'] }}</td>
                                <td data-label="{{ __('compliance.table.last') }}">
                                    @if ($row['last_kind'] !== null)
                                        <span class="text-strong">{{ $row['last_kind']->label() }}</span>
                                        <span class="sup-age font-mono whitespace-nowrap">{{ $row['last_at']?->format('d/m/Y') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="is-actions">
                                    <x-ui.button
                                        variant="secondary"
                                        size="sm"
                                        wire:click="toggle({{ $row['id'] }})"
                                        data-testid="compliance-open-{{ $row['id'] }}"
                                        data-row-action
                                    >{{ $this->open === $row['id'] ? __('compliance.close') : __('compliance.open') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.load-more :shown="$this->board->count()" :total="$this->matching" />
        @endif
    </x-ui.card>

    @if ($this->business !== null)
        <x-ui.slide-over
            class="is-wide"
            x-on:slide-over-close="$wire.close()"
            :title="$this->business->name"
            :subtitle="trans_choice('compliance.panel.subtitle', $this->timeline->count(), ['count' => $this->timeline->count()])"
        >
            <div class="sup-panel">
                <div class="sup-meta-row">
                    <span @class([
                        'status-tag',
                        'is-danger' => in_array($this->standing, [ComplianceEvent::SUSPENDED, ComplianceEvent::DEBT], true),
                        'is-warning' => $this->standing === ComplianceEvent::OBSERVED,
                        'is-success' => $this->standing === ComplianceEvent::CLEAR,
                    ])>{{ __('compliance.standing.'.$this->standing) }}</span>
                    <span class="text-muted text-sm">{{ __('compliance.standing_hint.'.$this->standing) }}</span>
                </div>

                {{-- The file in the order it happened, newest first. The answer
                (lifted, regularized) is drawn like the fault: it is part of it. --}}
                @forelse ($this->timeline as $event)
                    <div class="cmp-event" wire:key="event-{{ $event->id }}">
                        <span class="cmp-event-icon" data-tone="{{ $event->kind->tone() }}"><x-icon :name="$event->kind->icon()" :size="16" /></span>
                        <div class="cmp-event-body">
                            <div class="text-strong">{{ $event->kind->label() }}</div>
                            @if ($event->readableDetail() !== null)
                                <div class="text-muted text-sm">{{ $event->readableDetail() }}</div>
                            @endif
                            <div class="sup-age font-mono">
                                {{ $event->occurred_at->format('d/m/Y H:i') }}
                                @if ($event->actor !== null)
                                    · {{ __('compliance.by', ['name' => $event->actor->name]) }}
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-sm">{{ __('compliance.panel.empty') }}</p>
                @endforelse
            </div>
        </x-ui.slide-over>
    @endif
</div>
