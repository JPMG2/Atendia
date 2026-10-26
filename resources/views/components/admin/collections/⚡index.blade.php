<?php

use App\Classes\Main\CollectionsDesk;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who to ring today, worth first.
 *
 * Sorted by what is at stake and not by who has owed longest: the oldest
 * debt is usually the smallest, and the call that pays for itself is the
 * one on the account about to take the most money with it. The figures and
 * rows come from `CollectionsDesk`, which the mobile API reads too.
 */
new class extends Component
{
    #[Computed]
    public function desk(): CollectionsDesk
    {
        return new CollectionsDesk;
    }

    public function render(): View
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('collections.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('collections.title')" :sub="__('collections.sub')">
        <div class="flex flex-wrap items-center gap-3">
            <span class="sup-age">{{ $this->desk->readAt }}</span>
            @if (! $this->desk->isEmpty)
                <x-ui.export-menu report="cobranza" />
            @endif
        </div>
    </x-ui.page-head>

    @if ($this->desk->isEmpty)
        <x-ui.card class="p-5">
            <x-ui.empty-state
                icon="circle-check"
                :title="__('collections.empty_title')"
                :body="__('collections.empty_body')"
            />
        </x-ui.card>
    @else
        @php($cards = $this->desk->cards)
        {{-- The money first, the count second: three businesses owing is a
        number; what it costs if all three leave is a decision. --}}
        <div class="stat-grid stat-grid-fill mb-3">
            {{-- The hint goes in the slot, never in `delta`: a delta draws a
            trend arrow, and a green arrow up over money being lost reads as
            good news. --}}
            <x-ui.stat-card
                :label="$cards[0]['label']"
                :value="$cards[0]['value']"
                :icon="$cards[0]['icon']"
                :tint="$cards[0]['tint']"
            >{{ $cards[0]['hint'] }}</x-ui.stat-card>
            {{-- A count of businesses: it says which kind, how far behind, and
            lands on exactly those rows (atendiadesign §7.2). --}}
            <x-ui.stat-card
                :label="$cards[1]['label']"
                :value="$cards[1]['value']"
                :icon="$cards[1]['icon']"
                :tint="$cards[1]['tint']"
                :href="route('admin.businesses', ['estado' => 'problem'])"
            >{{ $cards[1]['hint'] }}</x-ui.stat-card>
            {{-- The comparison only exists once a month was photographed. With
            none, it says so: a first month measured against zero would read as
            growth that never happened. --}}
            <x-ui.stat-card
                :label="$cards[2]['label']"
                :value="$cards[2]['value']"
                :delta="$cards[2]['delta']"
                :trend="$cards[2]['trend']"
                :icon="$cards[2]['icon']"
                :tint="$cards[2]['tint']"
            >{{ $cards[2]['hint'] }}</x-ui.stat-card>
        </div>

        <x-ui.card class="p-5">
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('collections.table.business') }}</th>
                            <th>{{ __('collections.table.plan') }}</th>
                            <th>{{ __('collections.table.monthly') }}</th>
                            <th>{{ __('collections.table.state') }}</th>
                            <th>{{ __('collections.table.since') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->desk->rows as $row)
                            <tr wire:key="owing-{{ $row['id'] }}">
                                {{-- data-label: stacked below 991px the header row is gone,
                                and a cell without its name is a loose value. --}}
                                <td class="is-key" data-label="{{ __('collections.table.business') }}">{{ $row['business'] }}</td>
                                <td data-label="{{ __('collections.table.plan') }}">
                                    {{ $row['plan'] }}
                                    <span class="sup-age">{{ $row['cycle'] }}</span>
                                </td>
                                <td data-label="{{ __('collections.table.monthly') }}" class="font-mono">
                                    {{ $row['monthly'] }}
                                </td>
                                <td data-label="{{ __('collections.table.state') }}">
                                    <span @class([
                                        'status-tag',
                                        'is-danger' => $row['tone'] === 'danger',
                                        'is-warning' => $row['tone'] === 'warning',
                                        'is-neutral' => $row['tone'] === 'neutral',
                                    ])>{{ $row['state_label'] }}</span>
                                </td>
                                <td data-label="{{ __('collections.table.since') }}" class="font-mono">
                                    {{ $row['since'] }}
                                </td>
                                <td>
                                    <x-ui.button
                                        variant="ghost"
                                        size="sm"
                                        :href="route('admin.businesses', ['negocio' => $row['ficha']])"
                                        wire:navigate
                                        data-row-action
                                    >{{ __('collections.open_business') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <x-ui.key-hints />
</div>
