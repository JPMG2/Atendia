<?php

use App\Enums\SubscriptionStatus;
use App\Models\RevenueSnapshot;
use App\Models\Subscription;
use App\Traits\FormatsPrice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who to ring today, worth first.
 *
 * Sorted by what is at stake and not by who has owed longest: the oldest
 * debt is usually the smallest, and the call that pays for itself is the
 * one on the account about to take the most money with it.
 */
new class extends Component
{
    use FormatsPrice;

    /** @return Collection<int, Subscription> */
    #[Computed]
    public function queue(): Collection
    {
        return Subscription::collectionQueue();
    }

    #[Computed]
    public function atRisk(): float
    {
        return Subscription::amountAtRisk();
    }

    #[Computed]
    public function owing(): int
    {
        return $this->queue->filter(fn (Subscription $row): bool => $row->isBehind())->count();
    }

    #[Computed]
    public function revenue(): float
    {
        return Subscription::monthlyRecurringRevenue();
    }

    /**
     * Last month's photo, or null on the first month measured. Null is not
     * zero: comparing against a month that was never captured would print
     * growth out of thin air.
     */
    #[Computed]
    public function previous(): ?RevenueSnapshot
    {
        return RevenueSnapshot::before(CarbonImmutable::now());
    }

    /** What changed since that photo, in money. */
    #[Computed]
    public function change(): ?float
    {
        return $this->previous === null ? null : $this->revenue - (float) $this->previous->mrr;
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
        <span class="sup-age">{{ __('collections.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]) }}</span>
    </x-ui.page-head>

    @if ($this->queue->isEmpty())
        <x-ui.card class="p-5">
            <x-ui.empty-state
                icon="circle-check"
                :title="__('collections.empty_title')"
                :body="__('collections.empty_body')"
            />
        </x-ui.card>
    @else
        {{-- The money first, the count second: three businesses owing is a
        number; what it costs if all three leave is a decision. --}}
        <div class="stat-grid stat-grid-fill mb-3">
            {{-- The hint goes in the slot, never in `delta`: a delta draws a
            trend arrow, and a green arrow up over money being lost reads as
            good news. --}}
            <x-ui.stat-card
                :label="__('collections.risk.at_risk')"
                :value="'USD '.$this->formatPrice($this->atRisk)"
                icon="alert-triangle"
                tint="accent"
            >{{ __('collections.risk.at_risk_hint') }}</x-ui.stat-card>
            <x-ui.stat-card :label="__('collections.risk.owing')" :value="$this->owing" icon="receipt" tint="warning" />
            {{-- The comparison only exists once a month was photographed. With
            none, it says so: a first month measured against zero would read as
            growth that never happened. --}}
            <x-ui.stat-card
                :label="__('collections.risk.mrr')"
                :value="'USD '.$this->formatPrice($this->revenue)"
                :delta="$this->change === null
                    ? null
                    : __('collections.risk.vs_month', [
                        'amount' => ($this->change >= 0 ? '+' : '−').$this->formatPrice(abs($this->change)),
                        'month' => $this->previous->month->translatedFormat('F'),
                    ])"
                :trend="$this->change === null ? null : ($this->change > 0 ? 'up' : ($this->change < 0 ? 'down' : 'flat'))"
                icon="bar-chart-3"
                tint="brand"
            >@if ($this->change === null){{ __('collections.risk.no_history') }}@endif</x-ui.stat-card>
        </div>

        <x-ui.card class="p-5">
            <div class="pay-table-wrap">
                <table class="pay-table">
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
                        @foreach ($this->queue as $row)
                            <tr wire:key="owing-{{ $row->id }}">
                                {{-- data-label: stacked below 991px the header row is gone,
                                and a cell without its name is a loose value. --}}
                                <td data-label="{{ __('collections.table.business') }}">{{ $row->business?->name }}</td>
                                <td data-label="{{ __('collections.table.plan') }}">
                                    {{ __('plan.names.'.$row->plan) }}
                                    <span class="sup-age">{{ __('collections.cycle_'.$row->billing_cycle) }}</span>
                                </td>
                                <td data-label="{{ __('collections.table.monthly') }}" class="font-mono">
                                    {{ 'USD '.$this->formatPrice($row->monthlyValue()) }}
                                </td>
                                <td data-label="{{ __('collections.table.state') }}">
                                    @if ($row->status === SubscriptionStatus::Paused)
                                        <span class="status-tag is-danger">{{ __('collections.states.paused') }}</span>
                                    @elseif ($row->status === SubscriptionStatus::PastDue)
                                        <span class="status-tag is-warning">{{ __('collections.states.grace') }}</span>
                                    @else
                                        <span class="status-tag is-neutral">{{ __('collections.states.renewing') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('collections.table.since') }}" class="font-mono">
                                    @php($days = $row->daysUntilPayment())
                                    @if ($days === null)
                                        —
                                    @elseif ($days < 0)
                                        {{ trans_choice('collections.overdue', abs($days), ['count' => abs($days)]) }}
                                    @else
                                        {{ trans_choice('collections.due_in', $days, ['count' => $days]) }}
                                    @endif
                                </td>
                                <td>
                                    <x-ui.button
                                        variant="ghost"
                                        size="sm"
                                        :href="route('admin.businesses', ['negocio' => $row->business_id])"
                                        wire:navigate
                                    >{{ __('collections.open_business') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</div>
