<?php

use App\Classes\Main\Incidents;
use App\Dto\IncidentRowDto;
use App\Enums\IncidentKind;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Her operating desk: what the product got wrong today, with the evidence.
 *
 * Nothing is recorded for this screen. The signals were already there — a
 * thread whose last word is the customer's, a handoff nobody took, a reading
 * that came back annoyed, a job that died — and nobody read them together.
 */
new class extends Component
{
    #[Computed]
    public function desk(): Incidents
    {
        return Incidents::now();
    }

    /**
     * The rows of one kind. Filtering a loaded collection is presentation,
     * so the four tabs cost one read, not four queries.
     *
     * @return Collection<int, IncidentRowDto>
     */
    public function rowsOf(IncidentKind $kind): Collection
    {
        return $this->desk->ofKind($kind);
    }

    /**
     * The tabs, severity first, each with what it holds.
     *
     * @return list<array{value: string, label: string, icon: string, badge: int}>
     */
    #[Computed]
    public function tabs(): array
    {
        return collect(IncidentKind::cases())
            ->sortByDesc(fn (IncidentKind $kind): int => $kind->weight())
            ->map(fn (IncidentKind $kind): array => [
                'value' => $kind->value,
                'label' => __('incidents.kinds.'.$kind->value.'.tab'),
                'icon' => $kind->icon(),
                'badge' => $this->desk->countOf($kind),
            ])
            ->values()
            ->all();
    }

    /**
     * It opens on the worst thing that has something, not on the first tab:
     * a desk that opens empty hides the one row that needed her.
     */
    #[Computed]
    public function openTab(): string
    {
        $firstWithRows = collect($this->tabs())->firstWhere(fn (array $tab): bool => $tab['badge'] > 0);

        return $firstWithRows['value'] ?? IncidentKind::Unanswered->value;
    }

    public function render(): View
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('incidents.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('incidents.title')" :sub="__('incidents.sub')">
        <x-slot:inline>
            <span class="status-tag">{{ trans_choice('incidents.pending', $this->desk->all->count(), ['count' => $this->desk->all->count()]) }}</span>
        </x-slot:inline>
        {{-- The reading is live, but it says so: a count with no stamp gets
        trusted tomorrow morning as if it were fresh. --}}
        <span class="sup-age">{{ __('incidents.read_at', ['time' => $this->desk->readAt->translatedFormat('d/m/Y H:i')]) }}</span>
    </x-ui.page-head>

    @if ($this->desk->isEmpty)
        <x-ui.card class="p-5">
            <x-ui.empty-state
                icon="circle-check"
                :title="__('incidents.all_good_title')"
                :body="__('incidents.all_good_body')"
            />
        </x-ui.card>
    @else
        <x-ui.card class="p-5">
            {{-- The panels live INSIDE the slot: `tab` is Alpine state declared
            by the component, and a panel outside it has no scope to read. --}}
            <x-ui.tabs :tabs="$this->tabs()" :default="$this->openTab()">
            @foreach (IncidentKind::cases() as $kind)
                <div x-show="tab === '{{ $kind->value }}'" x-cloak wire:key="pane-{{ $kind->value }}">
                    <p class="sup-meta">{{ __('incidents.kinds.'.$kind->value.'.help') }}</p>

                    @php($rows = $this->rowsOf($kind))
                    @php($concentrated = $this->desk->concentration($kind))

                    {{-- Named above the table, never instead of it: the rows are
                    the customers to win back, this is who to call about them. --}}
                    @if ($concentrated !== null)
                        <x-ui.alert variant="warning" icon="triangle-alert" class="mb-3">
                            @if ($concentrated['count'] === $concentrated['total'])
                                {{ __('incidents.concentrated_all', ['count' => $concentrated['count'], 'business' => $concentrated['business']]) }}
                            @else
                                {{ __('incidents.concentrated', $concentrated) }}
                            @endif
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                :href="route('admin.businesses', ['negocio' => $concentrated['businessId']])"
                                wire:navigate
                            >{{ __('incidents.open_business') }}</x-ui.button>
                        </x-ui.alert>
                    @endif

                    @if ($rows->isEmpty())
                        <p class="sup-age">{{ __('incidents.none_of_kind') }}</p>
                    @else
                        <div class="pay-table-wrap">
                            <table class="pay-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('incidents.table.what') }}</th>
                                        <th>{{ __('incidents.table.business') }}</th>
                                        <th>{{ __('incidents.table.customer') }}</th>
                                        <th>{{ __('incidents.table.waiting') }}</th>
                                        <th>{{ __('incidents.table.last') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr wire:key="row-{{ $kind->value }}-{{ $loop->index }}">
                                            {{-- data-label: stacked below 991px the header row is
                                            gone, and a cell without its name is a loose value. --}}
                                            <td class="is-key" data-label="{{ __('incidents.table.what') }}">
                                                <span @class(['status-tag', 'is-danger' => $row->kind->tone() === 'danger', 'is-warning' => $row->kind->tone() === 'warning', 'is-neutral' => $row->kind->tone() === 'info'])>
                                                    {{ __('incidents.kinds.'.$row->kind->value.'.label') }}
                                                </span>
                                            </td>
                                            <td data-label="{{ __('incidents.table.business') }}">
                                                {{ $row->business ?? __('incidents.no_business') }}
                                            </td>
                                            <td data-label="{{ __('incidents.table.customer') }}" class="font-mono">
                                                {{ $row->customer ?? '—' }}
                                            </td>
                                            <td data-label="{{ __('incidents.table.waiting') }}" class="font-mono">
                                                @if ($row->minutesWaiting < 60)
                                                    {{ trans_choice('incidents.waiting_minutes', $row->minutesWaiting, ['count' => $row->minutesWaiting]) }}
                                                @elseif ($row->minutesWaiting < 1440)
                                                    {{ trans_choice('incidents.waiting_hours', intdiv($row->minutesWaiting, 60), ['count' => intdiv($row->minutesWaiting, 60)]) }}
                                                @else
                                                    {{ trans_choice('incidents.waiting_days', intdiv($row->minutesWaiting, 1440), ['count' => intdiv($row->minutesWaiting, 1440)]) }}
                                                @endif
                                            </td>
                                            <td data-label="{{ __('incidents.table.last') }}">
                                                <span class="inc-excerpt">{{ $row->excerpt ?? '—' }}</span>
                                            </td>
                                            <td>
                                                {{-- The action lives in the row, and only where it
                                                has somewhere real to go. --}}
                                                @if ($row->businessId !== null)
                                                    <x-ui.button
                                                        variant="ghost"
                                                        size="sm"
                                                        :href="route('admin.businesses', ['negocio' => $row->businessId])"
                                                        wire:navigate
                                                    >{{ __('incidents.open_business') }}</x-ui.button>
                                                @else
                                                    <x-ui.button
                                                        variant="ghost"
                                                        size="sm"
                                                        :href="route('admin.logs')"
                                                        wire:navigate
                                                    >{{ __('incidents.open_logs') }}</x-ui.button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
            </x-ui.tabs>
        </x-ui.card>
    @endif
</div>
