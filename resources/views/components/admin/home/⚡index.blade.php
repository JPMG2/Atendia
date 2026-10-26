<?php

use App\Enums\PlatformWhatsAppState;
use App\Enums\SubscriptionStatus;
use App\Services\PlatformWhatsApp;
use Carbon\CarbonImmutable;
use App\Livewire\Forms\Admin\PaymentReviewForm;
use App\Actions\Admin\RankBusinessActivity;
use App\Models\BusinessMention;
use App\Models\Payment;
use App\Models\PlatformFigure;
use App\Models\Subscription;
use App\Models\User;
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

    /**
     * Aliadito's own WhatsApp line when it is KNOWN to be down, for whoever may look at it.
     * "Not verified" is not down (the board has its own sensor for a silent bridge).
     *
     * @return array{state: PlatformWhatsAppState, since: ?CarbonImmutable}|null
     */
    #[Computed]
    public function lineDown(): ?array
    {
        if (auth()->user()?->can('integrations.view') !== true) {
            return null;
        }

        $line = PlatformWhatsApp::lastKnown();

        return $line['state']->isConnected() || $line['state'] === PlatformWhatsAppState::Unreachable
            ? null
            : ['state' => $line['state'], 'since' => $line['since']];
    }

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

    /** @return array{pending: int, not_following: int, ready: int, published: int} */
    #[Computed]
    public function mentions(): array
    {
        return BusinessMention::deskCounts();
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

    /** @return Collection<int, \App\Models\User> */
    #[Computed]
    public function twoFactorDue(): Collection
    {
        return auth()->user()->can('users.view') ? User::staffTwoFactorDueWithin(7) : new Collection;
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

    /**
     * The ranking a job worked out ahead of time, or null when it never ran.
     * Not counted here on purpose: grouping every message on each open is the
     * cost that does not scale with the number of businesses.
     */
    #[Computed]
    public function activity(): ?PlatformFigure
    {
        return PlatformFigure::read(PlatformFigure::ACTIVITY);
    }

    #[Computed]
    public function activityIsStale(): bool
    {
        return $this->activity?->isOlderThan(RankBusinessActivity::STALE_AFTER_MINUTES) ?? false;
    }

    /**
     * How the stored ranking is aging: it turns amber before it turns red, so
     * a late job is seen while there is still time to look at it.
     *
     * @return 'fresh'|'late'|'stale'|null Null when the job never ran.
     */
    #[Computed]
    public function activityBeat(): ?string
    {
        return match (true) {
            $this->activity === null => null,
            $this->activityIsStale => 'stale',
            $this->activity->isOlderThan(RankBusinessActivity::LATE_AFTER_MINUTES) => 'late',
            default => 'fresh',
        };
    }

    /**
     * Where a ranked business went against the period before. Rows stored
     * before the comparison existed carry no `previous_rank` and say nothing:
     * absent is not the same as new.
     *
     * @param  array{rank: int, previous_rank?: int|null}  $row
     * @return 'up'|'down'|'same'|'new'|null
     */
    public function trendOf(array $row): ?string
    {
        if (! array_key_exists('previous_rank', $row)) {
            return null;
        }

        return match (true) {
            $row['previous_rank'] === null => 'new',
            $row['previous_rank'] > $row['rank'] => 'up',
            $row['previous_rank'] < $row['rank'] => 'down',
            default => 'same',
        };
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
    </div>

    {{-- Only when somebody's plazo is about to end: with nobody due there is
    nothing to say, and an empty warning would train her to skip it. --}}
    @if ($this->twoFactorDue->isNotEmpty())
        <x-ui.alert variant="warning" icon="shield-check" class="mb-3" data-testid="two-factor-due">
            {{ trans_choice('admin.home.two_factor_due.text', $this->twoFactorDue->count(), [
                'count' => $this->twoFactorDue->count(),
                'names' => $this->twoFactorDue->pluck('name')->join(', ', ' y '),
            ]) }}
            <a class="row-link" href="{{ route('admin.users', ['doble' => 'off']) }}" wire:navigate>{{ __('admin.home.two_factor_due.see') }}</a>
        </x-ui.alert>
    @endif

    {{-- One island for everything a payment can move: the tiles, the queue and
    the subscription tabs. Polling only the queue would leave a tile counting
    a receipt the table no longer shows (atendiadesign §7.1). Crediting from a
    row inside re-renders the same region, so it stays one truth. The 30 s of
    the poll and of the copy below are the same number. --}}
    @island(name: 'board')
    <div wire:poll.{{ \App\Classes\Main\PlatformBoard::POLL_SECONDS }}s.visible>
    {{-- Inside the island so it clears itself when the line comes back; the lock
    for linking is the route's permission, the button is only courtesy. --}}
    @if ($this->lineDown !== null)
        <x-ui.alert variant="danger" icon="triangle-alert" class="mb-3" data-testid="platform-line-down">
            {{ __('admin.home.line_down.text', ['brand' => \App\Models\Company::brand()]) }}
            @if ($this->lineDown['since'] !== null)
                {{ __('admin.home.line_down.since', ['ago' => $this->lineDown['since']->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true)]) }}.
            @endif
            @can('integrations.manage')
                <a class="row-link" href="{{ route('admin.platform-whatsapp') }}" wire:navigate>{{ __('admin.home.line_down.link') }}</a>
            @endcan
        </x-ui.alert>
    @endif

    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <span class="text-subtle text-sm">{{ __('admin.home.live', ['seconds' => \App\Classes\Main\PlatformBoard::POLL_SECONDS]) }}</span>
        <span class="flex items-center gap-3">
            <x-ask.admin-button :question="__('ask_admin.ask.pulse')" />
            <span class="text-subtle font-mono text-sm">
                {{ __('admin.home.as_of', ['date' => now()->format('d/m/Y H:i:s')]) }}
            </span>
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
            :href="route('admin.businesses', ['estado' => 'canceling'])"
        >{{ $this->leaving->isEmpty()
            ? __('admin.home.tiles.leaving_none')
            : __('admin.home.tiles.leaving_amount', ['amount' => number_format($this->leavingAmount, 2, ',', '.')]) }}</x-ui.stat-card>

        <x-ui.stat-card
            :label="__('admin.home.tiles.mentions')"
            :value="(string) $this->mentions['pending']"
            icon="star"
            :tint="$this->mentions['pending'] > 0 ? 'warning' : 'brand'"
            :href="route('admin.mentions')"
        >{{ $this->mentions['pending'] + $this->mentions['ready'] === 0
            ? __('admin.home.tiles.mentions_none')
            : trans_choice('admin.home.tiles.mentions_ready', $this->mentions['ready'], ['count' => $this->mentions['ready']]) }}</x-ui.stat-card>

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
        <div class="bp-card-head"><h2>{{ __('admin.home.queue.title') }}</h2><x-ask.admin-button :question="__('ask_admin.ask.queue')" /></div>
        <p class="bp-card-sub">{{ __('admin.home.queue.sub') }}</p>

        @if ($this->queue->isEmpty())
            <p class="text-muted text-sm">{{ __('admin.home.queue.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
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
                                <td class="is-key" data-label="{{ __('admin.home.queue.business') }}">
                                    <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $payment->business_id]) }}">{{ $payment->business?->name }}</a>
                                </td>
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

    {{-- Three lists answering one question (what happens with the
    subscriptions this week) in ONE card with tabs. Four stacked tables made
    her read the whole screen to find the row she had to act on. --}}
    <x-ui.card class="bp-card mt-3">
        {{-- It opens on what is stuck, not on the first tab: a business that
        did not pay beats one that renews on Friday. --}}
        <x-ui.tabs
            :default="$this->struggling->isNotEmpty() ? 'struggling' : 'renewals'"
            :tabs="[
                ['value' => 'renewals', 'label' => __('admin.home.renewals.tab'), 'icon' => 'repeat', 'badge' => $this->renewals->count()],
                ['value' => 'leaving', 'label' => __('admin.home.leaving.tab'), 'icon' => 'log-out', 'badge' => $this->leaving->count()],
                ['value' => 'struggling', 'label' => __('admin.home.struggling.tab'), 'icon' => 'alert-triangle', 'badge' => $this->struggling->count()],
            ]"
        >
        <div x-show="tab === 'renewals'" x-cloak>
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
                <table class="pay-table" data-sortable>
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
                                <td data-label="{{ __('admin.home.renewals.business') }}">
                                    <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $subscription->business_id]) }}">{{ $subscription->business?->name }}</a>
                                </td>
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
        </div>

        <div x-show="tab === 'leaving'" x-cloak>
            <div class="bp-card-head"><h2>{{ __('admin.home.leaving.title') }}</h2><x-ask.admin-button :question="__('ask_admin.ask.leaving')" /></div>
            <p class="bp-card-sub">{{ __('admin.home.leaving.sub') }}</p>

            @if ($this->leaving->isEmpty())
                <p class="text-muted text-sm">{{ __('admin.home.leaving.empty') }}</p>
            @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
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
                                <td data-label="{{ __('admin.home.leaving.business') }}">
                                    <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $subscription->business_id]) }}">{{ $subscription->business?->name }}</a>
                                </td>
                                <td data-label="{{ __('admin.home.leaving.plan') }}">{{ __('plan.names.'.$subscription->plan) }}</td>
                                <td class="font-mono" data-label="{{ __('admin.home.leaving.amount') }}">USD {{ number_format($subscription->nextAmount(), 2, ',', '.') }}</td>
                                <td class="font-mono" data-label="{{ __('admin.home.leaving.asked') }}">{{ $subscription->canceled_at?->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div x-show="tab === 'struggling'" x-cloak>
        <div class="bp-card-head"><h2>{{ __('admin.home.struggling.title') }}</h2><x-ask.admin-button :question="__('ask_admin.ask.struggling')" /></div>
        <p class="bp-card-sub">{{ __('admin.home.struggling.sub') }}</p>

        @if ($this->struggling->isEmpty())
            <p class="text-muted text-sm">{{ __('admin.home.struggling.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
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
                                <td data-label="{{ __('admin.home.struggling.business') }}">
                                    <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $subscription->business_id]) }}">{{ $subscription->business?->name }}</a>
                                </td>
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

            {{-- The card's own door: the same list this table previews, cut by
            the pair of states it shows (atendiadesign §7.2). --}}
            <div class="bp-card-actions">
                <x-ui.button variant="ghost" size="sm" :href="route('admin.businesses', ['estado' => 'problem'])" wire:navigate>
                    {{ __('admin.home.struggling.see_all') }}
                </x-ui.button>
            </div>
        @endif
        </div>
        </x-ui.tabs>
    </x-ui.card>
    </div>
    @endisland

    {{-- Worked out by `atendia:business-activity`, so it carries its own age:
    a stored ranking that hid how old it is would read as live (§7.1). --}}
    <x-ui.card class="bp-card mt-3" data-testid="activity-card">
        <div class="bp-card-head">
            <h2>{{ __('admin.home.activity.title') }}</h2>
            @if ($this->activity !== null)
                {{-- The dot is the job's pulse: green on time, amber once it is
                late, red when it is overdue. The words say the same, so colour
                is never the only signal. --}}
                <span
                    class="beat-chip"
                    title="{{ __('admin.home.activity.beat.'.$this->activityBeat) }}"
                    data-testid="activity-beat"
                    data-beat="{{ $this->activityBeat }}"
                >
                    <span class="beat-dot" aria-hidden="true"></span>
                    <span class="beat-word">{{ __('admin.home.activity.beat_word.'.$this->activityBeat) }}</span>
                    <span class="beat-date font-mono">{{ __('admin.home.activity.computed', ['date' => $this->activity->computed_at->format('d/m/Y H:i')]) }}</span>
                </span>
            @endif
        </div>

        @if ($this->activity === null)
            <p class="text-muted text-sm">{{ __('admin.home.activity.never') }}</p>
        @else
            <p class="bp-card-sub">{{ __('admin.home.activity.sub', ['days' => $this->activity->payload['days']]) }}</p>

            @if ($this->activityIsStale)
                <x-ui.alert variant="warning" icon="clock" class="mb-3" data-testid="activity-stale">
                    {{ __('admin.home.activity.stale', ['date' => $this->activity->computed_at->format('d/m/Y H:i')]) }}
                </x-ui.alert>
            @endif

            {{-- Two rankings, one table at a time: side by side each half is
            narrower than a table's own minimum and would scroll sideways. --}}
            <x-ui.tabs
                :tabs="[
                    ['value' => 'whatsapp', 'label' => __('admin.home.activity.tab_whatsapp'), 'icon' => 'message-circle'],
                    ['value' => 'ai', 'label' => __('admin.home.activity.tab_ai'), 'icon' => 'bot'],
                ]"
            >
                @foreach (['whatsapp' => 'unit', 'ai' => 'unit_ai'] as $board => $unit)
                    <div x-show="tab === '{{ $board }}'" x-cloak>
                        <p class="text-muted mb-1 text-sm">{{ __('admin.home.activity.'.$board) }}</p>

                        @if ($this->activity->payload[$board] === [])
                            <p class="text-muted text-sm">{{ __('admin.home.activity.empty') }}</p>
                        @else
                            <div class="pay-table-wrap">
                                <table class="pay-table">
                                    <tbody>
                                        @foreach ($this->activity->payload[$board] as $row)
                                            <tr wire:key="activity-{{ $board }}-{{ $row['business_id'] }}">
                                                <td class="is-key" data-label="{{ __('admin.home.queue.business') }}">
                                                    <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $row['business_id']]) }}">{{ $row['name'] }}</a>
                                                    @php($trend = $this->trendOf($row))
                                                    @if ($trend !== null)
                                                        @php($moved = abs(($row['previous_rank'] ?? $row['rank']) - $row['rank']))
                                                        <span
                                                            @class([
                                                                'status-tag ml-2',
                                                                'is-success' => $trend === 'up',
                                                                'is-danger' => $trend === 'down',
                                                                'is-info' => $trend === 'new',
                                                            ])
                                                            title="{{ trans_choice('admin.home.activity.trend.'.$trend, $moved, ['count' => $moved]) }}"
                                                            data-testid="activity-trend-{{ $trend }}"
                                                        >
                                                            <x-icon :name="match ($trend) { 'up' => 'chevron-up', 'down' => 'chevron-down', 'new' => 'sparkles', default => 'minus' }" :size="12" />
                                                            {{ match ($trend) {
                                                                'new' => __('admin.home.activity.trend.new_short'),
                                                                'same' => '',
                                                                default => $moved,
                                                            } }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="font-mono" data-label="{{ __('admin.home.activity.col_total') }}">
                                                    {{ trans_choice('admin.home.activity.'.$unit, $row['total'], ['count' => number_format($row['total'], 0, ',', '.')]) }}
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
        @endif
    </x-ui.card>

    <x-ui.key-hints />
</div>
