<?php

use App\Enums\SubscriptionStatus;
use App\Livewire\Forms\Admin\PaymentReviewForm;
use App\Models\Payment;
use App\Models\Subscription;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The admin's home: what is waiting for a decision, with the rows behind each
 * number on the same screen. Every figure is counted here and now, so it
 * carries the moment it was read (atendiadesign §7.1).
 */
new class extends Component
{
    use HasNotifications;

    /** How many waiting receipts the home shows before sending her to Pagos. */
    private const QUEUE_PREVIEW = 5;

    public PaymentReviewForm $form;

    #[Computed]
    public function receipts(): int
    {
        return Payment::pendingReviewCount();
    }

    /** @return Collection<int, Payment> */
    #[Computed]
    public function queue(): Collection
    {
        return Payment::reviewQueue(self::QUEUE_PREVIEW);
    }

    #[Computed]
    public function oldestWait(): ?int
    {
        return Payment::oldestPendingAgeInDays();
    }

    #[Computed]
    public function collected(): float
    {
        return Payment::collectedInTheLastDays(30);
    }

    /** What actually came in over the last seven days, to read beside what is due. */
    #[Computed]
    public function collectedLastWeek(): float
    {
        return Payment::collectedInTheLastDays(7);
    }

    #[Computed]
    public function renewalsAmount(): float
    {
        return (float) $this->renewals->sum(fn (Subscription $subscription): float => $subscription->nextAmount());
    }

    /** Crediting from here is the same verdict as in Pagos: one Form, one action. */
    public function approve(int $id): void
    {
        $this->dispatchNotification($this->form->approve($id));

        unset($this->queue, $this->receipts, $this->oldestWait, $this->collected, $this->collectedLastWeek);
    }

    #[Computed]
    public function planChanges(): int
    {
        return Payment::planChangesAwaitingReview();
    }

    /** @return Collection<int, Subscription> */
    #[Computed]
    public function renewals(): Collection
    {
        return Subscription::renewingWithin(7);
    }

    /** @return Collection<int, Subscription> */
    #[Computed]
    public function struggling(): Collection
    {
        return Subscription::struggling();
    }

    /** @return Collection<int, Subscription> */
    #[Computed]
    public function leaving(): Collection
    {
        return Subscription::scheduledCancellations();
    }

    /** The monthly money that walks out with them, so the count has a weight. */
    #[Computed]
    public function leavingAmount(): float
    {
        return (float) $this->leaving->sum(fn (Subscription $subscription): float => $subscription->nextAmount());
    }

    #[Computed]
    public function monthlyRevenue(): float
    {
        return Subscription::monthlyRecurringRevenue();
    }

    /**
     * How many subscriptions the revenue above is made of. Zero revenue with
     * this at zero is "nobody pays yet", which is a different sentence from
     * "nobody paid this month" — the tile says which one it is.
     */
    #[Computed]
    public function payingCount(): int
    {
        return Subscription::payingCount();
    }

    #[Computed]
    public function trialCount(): int
    {
        return Subscription::trialingCount();
    }

    public function render(): View
    {
        return $this->view()->title(__('admin.home.title'));
    }
}; ?>

<div class="bp-main">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('admin.home.title') }}</h1>
            <p class="page-head-sub">{{ __('admin.home.sub') }}</p>
        </div>
        <span class="text-subtle font-mono text-sm">
            {{ __('admin.home.as_of', ['date' => now()->format('d/m/Y H:i')]) }}
        </span>
    </div>

    <div class="stat-grid stat-grid-fill">
        <x-ui.stat-card
            :label="__('admin.home.tiles.receipts')"
            :value="(string) $this->receipts"
            icon="receipt"
            :tint="$this->receipts > 0 ? 'warning' : 'brand'"
            :href="route('admin.payments')"
        >@if ($this->receipts === 0)
                {{ __('admin.home.tiles.receipts_none') }}
            @elseif ($this->oldestWait === 0)
                {{ __('admin.home.tiles.receipts_waiting_today') }}
            @else
                {{ trans_choice('admin.home.tiles.receipts_waiting', (int) $this->oldestWait, ['days' => $this->oldestWait]) }}
            @endif</x-ui.stat-card>

        <x-ui.stat-card
            :label="__('admin.home.tiles.plan_changes')"
            :value="(string) $this->planChanges"
            icon="repeat"
            tint="info"
            :href="route('admin.payments')"
        >{{ __('admin.home.tiles.plan_changes_hint') }}</x-ui.stat-card>

        <x-ui.stat-card
            :label="__('admin.home.tiles.leaving')"
            :value="(string) $this->leaving->count()"
            icon="log-out"
            :tint="$this->leaving->isNotEmpty() ? 'accent' : 'brand'"
            :href="route('admin.businesses')"
        >{{ $this->leaving->isEmpty()
            ? __('admin.home.tiles.leaving_none')
            : __('admin.home.tiles.leaving_amount', ['amount' => number_format($this->leavingAmount, 2, ',', '.')]) }}</x-ui.stat-card>

        {{-- Revenue is a figure, not a queue: there is nowhere to drill into, so
        it carries its context instead of a door. --}}
        <x-ui.stat-card
            :label="__('admin.home.tiles.mrr')"
            :value="'USD '.number_format($this->monthlyRevenue, 2, ',', '.')"
            icon="bar-chart-3"
            :tint="$this->payingCount > 0 ? 'brand' : 'accent'"
        >{{ $this->payingCount > 0
            ? __('admin.home.tiles.mrr_from', ['count' => $this->payingCount])
            : __('admin.home.tiles.mrr_none', ['count' => $this->trialCount]) }}<br>{{
            __('admin.home.tiles.mrr_collected', ['amount' => number_format($this->collected, 2, ',', '.')]) }}</x-ui.stat-card>
    </div>

    {{-- The queue comes first: what is stuck waiting for her beats what is
    merely coming (atendiadesign §7.2). --}}
    <x-ui.card class="bp-card mt-3">
        <div class="bp-card-head"><h2>{{ __('admin.home.queue.title') }}</h2></div>
        <p class="bp-card-sub">{{ __('admin.home.queue.sub') }}</p>

        @if ($this->queue->isEmpty())
            <p class="text-muted text-sm">{{ __('admin.home.queue.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.home.queue.date') }}</th>
                            <th>{{ __('admin.home.queue.business') }}</th>
                            <th>{{ __('admin.home.queue.plan') }}</th>
                            <th>{{ __('admin.home.queue.amount') }}</th>
                            <th>{{ __('admin.home.queue.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->queue as $payment)
                            <tr wire:key="queue-{{ $payment->id }}">
                                <td class="font-mono" data-label="{{ __('admin.home.queue.date') }}">{{ $payment->created_at?->format('d/m/Y') }}</td>
                                <td data-label="{{ __('admin.home.queue.business') }}">{{ $payment->business?->name }}</td>
                                <td data-label="{{ __('admin.home.queue.plan') }}">{{ __('plan.names.'.$payment->plan) }}</td>
                                <td class="font-mono" data-label="{{ __('admin.home.queue.amount') }}">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                <td data-label="{{ __('admin.home.queue.action') }}">
                                    <x-ui.button
                                        size="sm"
                                        wire:click="approve({{ $payment->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="approve({{ $payment->id }})"
                                        data-testid="home-approve-{{ $payment->id }}"
                                    >{{ __('admin.home.queue.approve') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->receipts > $this->queue->count())
                <div class="bp-card-actions">
                    <x-ui.button variant="ghost" size="sm" :href="route('admin.payments')" wire:navigate>
                        {{ __('admin.home.queue.see_all', ['count' => $this->receipts]) }}
                    </x-ui.button>
                </div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card class="bp-card mt-3">
        <div class="bp-card-head">
            <h2>{{ __('admin.home.renewals.title') }}</h2>
            @if ($this->renewals->isNotEmpty())
                <span class="text-strong font-mono text-sm">
                    {{ __('admin.home.renewals.expected', ['amount' => number_format($this->renewalsAmount, 2, ',', '.')]) }}
                </span>
            @endif
        </div>
        {{-- What is due, read beside what actually came in: a figure with
        nothing to compare against says nothing (atendiadesign §7.1). --}}
        <p class="bp-card-sub">
            {{ __('admin.home.renewals.sub') }}
            {{ __('admin.home.renewals.vs_last_week', ['amount' => number_format($this->collectedLastWeek, 2, ',', '.')]) }}
        </p>

        @if ($this->renewals->isEmpty())
            <p class="text-muted text-sm">{{ __('admin.home.renewals.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.home.renewals.due') }}</th>
                            <th>{{ __('admin.home.renewals.business') }}</th>
                            <th>{{ __('admin.home.renewals.plan') }}</th>
                            <th>{{ __('admin.home.renewals.cycle') }}</th>
                            <th>{{ __('admin.home.renewals.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- data-label: stacked on a phone the header row is gone,
                        so each cell has to say what it is. --}}
                        @foreach ($this->renewals as $subscription)
                            <tr>
                                <td class="font-mono" data-label="{{ __('admin.home.renewals.due') }}">{{ $subscription->periodEndsAt()?->format('d/m/Y') }}</td>
                                <td data-label="{{ __('admin.home.renewals.business') }}">{{ $subscription->business?->name }}</td>
                                <td data-label="{{ __('admin.home.renewals.plan') }}">{{ __('plan.names.'.$subscription->plan) }}</td>
                                <td data-label="{{ __('admin.home.renewals.cycle') }}">
                                    @if ($subscription->status === SubscriptionStatus::Trialing)
                                        <span class="status-tag">{{ __('admin.home.renewals.trial') }}</span>
                                    @else
                                        {{ $subscription->billing_cycle === 'yearly'
                                            ? __('admin.home.renewals.yearly')
                                            : __('admin.home.renewals.monthly') }}
                                    @endif
                                </td>
                                <td class="font-mono" data-label="{{ __('admin.home.renewals.amount') }}">USD {{ number_format($subscription->nextAmount(), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($this->leaving->isNotEmpty())
        <x-ui.card class="bp-card mt-3">
            <div class="bp-card-head"><h2>{{ __('admin.home.leaving.title') }}</h2></div>
            <p class="bp-card-sub">{{ __('admin.home.leaving.sub') }}</p>

            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.home.leaving.ends') }}</th>
                            <th>{{ __('admin.home.leaving.business') }}</th>
                            <th>{{ __('admin.home.leaving.plan') }}</th>
                            <th>{{ __('admin.home.leaving.amount') }}</th>
                            <th>{{ __('admin.home.leaving.asked') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->leaving as $subscription)
                            <tr wire:key="leaving-{{ $subscription->id }}">
                                <td class="font-mono" data-label="{{ __('admin.home.leaving.ends') }}">{{ $subscription->periodEndsAt()?->format('d/m/Y') }}</td>
                                <td data-label="{{ __('admin.home.leaving.business') }}">{{ $subscription->business?->name }}</td>
                                <td data-label="{{ __('admin.home.leaving.plan') }}">{{ __('plan.names.'.$subscription->plan) }}</td>
                                <td class="font-mono" data-label="{{ __('admin.home.leaving.amount') }}">USD {{ number_format($subscription->nextAmount(), 2, ',', '.') }}</td>
                                <td class="font-mono" data-label="{{ __('admin.home.leaving.asked') }}">{{ $subscription->canceled_at?->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card class="bp-card mt-3">
        <div class="bp-card-head"><h2>{{ __('admin.home.struggling.title') }}</h2></div>
        <p class="bp-card-sub">{{ __('admin.home.struggling.sub') }}</p>

        @if ($this->struggling->isEmpty())
            <p class="text-muted text-sm">{{ __('admin.home.struggling.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.home.struggling.since') }}</th>
                            <th>{{ __('admin.home.struggling.business') }}</th>
                            <th>{{ __('admin.home.struggling.plan') }}</th>
                            <th>{{ __('admin.home.struggling.state') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->struggling as $subscription)
                            <tr>
                                <td class="font-mono" data-label="{{ __('admin.home.struggling.since') }}">{{ $subscription->periodEndsAt()?->format('d/m/Y') }}</td>
                                <td data-label="{{ __('admin.home.struggling.business') }}">{{ $subscription->business?->name }}</td>
                                <td data-label="{{ __('admin.home.struggling.plan') }}">{{ __('plan.names.'.$subscription->plan) }}</td>
                                <td data-label="{{ __('admin.home.struggling.state') }}">
                                    @if ($subscription->status === SubscriptionStatus::Paused)
                                        <span class="status-tag is-danger">{{ __('admin.home.struggling.paused') }}</span>
                                    @else
                                        <span class="status-tag">{{ __('admin.home.struggling.past_due') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
