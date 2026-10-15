<?php

use App\Actions\Admin\FindOrphanInstances;
use App\Actions\Admin\ReleaseBridgeInstance;
use App\Actions\Admin\RemoveBusiness;
use App\Actions\Admin\RestoreBusiness;
use App\Actions\Billing\CancelSubscription;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Business;
use App\Models\ComplianceEvent;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The ledger of everyone the platform serves: plan, state, what they owe and
 * when they renew, with each business's money history one click away.
 * `/admin/adopcion` measures the funnel; this screen administers.
 */
new class extends Component
{
    use HasNotifications;

    /**
     * The open row, in the URL: a tile of the Inicio that counts three
     * businesses has to be able to LAND on one of them, not on the list.
     */
    #[Url(as: 'negocio')]
    public ?int $open = null;

    /** The state the list is cut by, also in the URL, for the same reason. */
    #[Url(as: 'estado')]
    public string $state = '';

    /** What she typed to find one business by name. */
    #[Url(as: 'buscar')]
    public string $search = '';

    public function clearFilters(): void
    {
        $this->reset('search', 'state');
    }

    /** The list walks in tranches: a platform with thousands of businesses is never loaded whole. */
    private const int PAGE = 25;

    public int $visible = self::PAGE;

    public function loadMore(): void
    {
        $this->visible += self::PAGE;
    }

    /** A new cut starts from the top again, not from wherever she had scrolled to. */
    public function updatedSearch(): void
    {
        $this->visible = self::PAGE;
    }

    public function updatedState(): void
    {
        $this->visible = self::PAGE;
    }

    #[Computed]
    public function total(): int
    {
        return Business::directoryTotal($this->state);
    }

    /** How many match the search and the state, of which the page shows `visible`. */
    #[Computed]
    public function matching(): int
    {
        return Business::directoryMatching($this->search, $this->state);
    }

    /** @return Collection<int, Business> */
    #[Computed]
    public function businesses(): Collection
    {
        return Business::directory($this->search, $this->state, $this->visible);
    }

    /**
     * What the list can be cut by. "problem" is the one group: it is the pair
     * the Inicio shows together under "les está costando pagar".
     *
     * @return array<string, string>
     */
    #[Computed]
    public function stateOptions(): array
    {
        return collect(['problem', 'trialing', 'active', 'past_due', 'paused', 'canceling', 'ended', 'suspended', 'none', 'removed'])
            ->mapWithKeys(fn (string $state): array => [$state => __('admin.businesses.states.'.$state)])
            ->all();
    }

    #[Computed]
    public function ficha(): ?Business
    {
        return $this->open === null ? null : Business::ledger($this->open);
    }

    /** How many lines its compliance history holds: what the link to it says. */
    #[Computed]
    public function historyCount(): int
    {
        return $this->open === null ? 0 : ComplianceEvent::countOf($this->open);
    }

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;

        unset($this->ficha);
    }

    public function cancel(int $id): void
    {
        $this->dispatchNotification($this->verdict($id, cancel: true));
    }

    public function undoCancel(int $id): void
    {
        $this->dispatchNotification($this->verdict($id, cancel: false));
    }

    /**
     * The one action here that can fail outside the database (it frees the
     * bridge instance): the answer says so, and nothing was changed.
     */
    public function remove(int $id): void
    {
        $business = Business::ledger($id);

        if ($business === null || $business->trashed()) {
            return;
        }

        try {
            app(RemoveBusiness::class)->handle($business);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            $this->dispatchNotification(new NotificationDto(__('admin.businesses.remove.failed'), NotificationType::Error));

            return;
        }

        $this->open = null;
        unset($this->businesses, $this->ficha, $this->total, $this->matching);

        $this->dispatchNotification(new NotificationDto(__('admin.businesses.remove.done'), NotificationType::Success));
    }

    /** What removing the open business gives back, in a sentence the confirmation repeats. */
    #[Computed]
    public function frees(): string
    {
        if ($this->ficha === null) {
            return '';
        }

        $held = $this->ficha->releasedOnRemoval();

        $items = array_values(array_filter([
            $held['instance'] === null ? null : __('admin.businesses.remove.item_instance', [
                'instance' => $held['instance'],
                'state' => $held['connected'] ? __('admin.businesses.remove.connected') : __('admin.businesses.remove.not_connected'),
            ]),
            $held['phone'] === null ? null : __('admin.businesses.remove.item_phone', ['phone' => $held['phone']]),
            $held['people'] === 0 ? null : trans_choice('admin.businesses.remove.item_people', $held['people'], ['count' => $held['people']]),
        ]));

        return $items === []
            ? __('admin.businesses.remove.frees_nothing')
            : __('admin.businesses.remove.frees', ['items' => implode(', ', $items)]);
    }

    public function restore(int $id): void
    {
        $business = Business::ledger($id);

        if ($business === null || ! $business->trashed()) {
            return;
        }

        app(RestoreBusiness::class)->handle($business);

        $this->state = '';
        unset($this->businesses, $this->ficha, $this->total, $this->matching);

        $this->dispatchNotification(new NotificationDto(__('admin.businesses.restore.done'), NotificationType::Success));
    }

    /**
     * What the last look at the bridge found, with its hour: the screen never
     * calls the bridge on a render, the sweep does.
     *
     * @return array{names: list<string>, at: Carbon\CarbonImmutable}|null
     */
    #[Computed]
    public function orphans(): ?array
    {
        $seen = FindOrphanInstances::lastKnown();

        return $seen === null || $seen['names'] === [] ? null : $seen;
    }

    public function releaseOrphan(string $instance): void
    {
        try {
            $freed = app(ReleaseBridgeInstance::class)->handle($instance);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            $this->dispatchNotification(new NotificationDto(__('admin.businesses.remove.failed'), NotificationType::Error));

            return;
        }

        unset($this->orphans);

        $this->dispatchNotification(new NotificationDto(
            $freed ? __('admin.businesses.orphans.done') : __('admin.businesses.orphans.refused'),
            $freed ? NotificationType::Success : NotificationType::Error,
        ));
    }

    /**
     * Both verdicts are the same walk: find the plan row, act on it, forget
     * what the screen had cached. Only the Action knows what leaving means.
     */
    private function verdict(int $id, bool $cancel): NotificationDto
    {
        $subscription = Business::ledger($id)?->subscription;

        if ($subscription === null) {
            return new NotificationDto(__('admin.businesses.cancel.no_subscription'), NotificationType::Error);
        }

        $cancel
            ? app(CancelSubscription::class)->handle($subscription)
            : app(CancelSubscription::class)->undo($subscription);

        unset($this->businesses, $this->ficha);

        return new NotificationDto(
            $cancel ? __('admin.businesses.cancel.done') : __('admin.businesses.cancel.undone'),
            NotificationType::Success,
        );
    }

    /** The one word that names a business's situation, worst news first. */
    public function stateOf(Business $business): string
    {
        return $business->situation();
    }

    public function render(): View
    {
        return $this->view()->title(__('admin.businesses.title'));
    }
}; ?>

<div class="bp-main" x-data="adminBusinesses">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('admin.businesses.title') }}</h1>
            <p class="page-head-sub">{{ __('admin.businesses.sub') }}</p>
        </div>
        <x-ui.result-count :shown="$this->matching" :total="$this->total" noun="admin.businesses.count" />
    </div>

    {{-- Instances the bridge keeps running for nobody: the sweep found them, here they are freed. --}}
    @if ($this->orphans !== null && auth()->user()->can('businesses.manage'))
        <x-ui.card class="bp-card mb-3" data-testid="bridge-orphans">
            <div class="bp-card-head">
                <h2>{{ __('admin.businesses.orphans.title') }}</h2>
                <span class="text-subtle text-xs font-mono">{{ __('admin.businesses.orphans.as_of', ['date' => $this->orphans['at']->format('d/m H:i')]) }}</span>
            </div>
            <p class="bp-card-sub">{{ __('admin.businesses.orphans.sub') }}</p>

            @foreach ($this->orphans['names'] as $instance)
                <div class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 hover:bg-sunken" wire:key="orphan-{{ $instance }}">
                    <span class="font-mono text-sm">{{ $instance }}</span>
                    <x-ui.button
                        variant="danger"
                        size="sm"
                        x-on:click="confirmRelease({{ Illuminate\Support\Js::from($instance) }})"
                        data-testid="orphan-release-{{ $instance }}"
                    >{{ __('admin.businesses.orphans.action') }}</x-ui.button>
                </div>
            @endforeach
        </x-ui.card>
    @endif

    {{-- Master-detail: the list never moves, the ficha sits beside it. --}}
    <div @class(['split-view', 'has-detail' => $this->ficha !== null])>
    <x-ui.card class="bp-card">
        <x-catalog.form-row>
            <x-inputsform.input
                size="s"
                span="text"
                :label="__('admin.businesses.search')"
                name="search"
                icon="search"
                data-key-focus="/"
                :placeholder="__('admin.businesses.search_placeholder')"
                wire:model.live.debounce.400ms="search"
            />

            <x-inputsform.combobox
                size="s"
                span="short"
                :label="__('admin.businesses.state')"
                name="state"
                :options="$this->stateOptions"
                :value="$state"
                :placeholder="__('admin.businesses.all_states')"
                wire:model.live="state"
            />

            @if ($search !== '' || $state !== '')
                <x-ui.button variant="ghost" size="sm" wire:click="clearFilters" data-key-click="c">
                    {{ __('admin.keys.clear_filters') }}
                </x-ui.button>
            @endif
        </x-catalog.form-row>

        @if ($this->businesses->isEmpty())
            {{-- Nothing found is not the same sentence as nothing exists. --}}
            <p class="text-muted text-sm">
                {{ $search === '' && $state === '' ? __('admin.businesses.empty') : __('admin.businesses.no_match') }}
            </p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('admin.businesses.name') }}</th>
                            <th class="col-aux">{{ __('admin.businesses.plan') }}</th>
                            <th>{{ __('admin.businesses.state') }}</th>
                            <th class="col-aux">{{ __('admin.businesses.due') }}</th>
                            <th>{{ __('admin.businesses.owes') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->businesses as $business)
                            @php($state = $this->stateOf($business))
                            <tr wire:key="biz-{{ $business->id }}">
                                <td class="is-key" data-label="{{ __('admin.businesses.name') }}">{{ $business->name }}</td>
                                <td class="col-aux" data-label="{{ __('admin.businesses.plan') }}">
                                    {{ $business->subscription ? __('plan.names.'.$business->subscription->plan) : '—' }}
                                </td>
                                <td data-label="{{ __('admin.businesses.state') }}">
                                    <span @class([
                                        'status-tag',
                                        'is-danger' => in_array($state, ['suspended', 'paused', 'ended', 'removed'], true),
                                    ])>{{ __('admin.businesses.states.'.$state) }}</span>
                                </td>
                                <td class="font-mono col-aux" data-label="{{ __('admin.businesses.due') }}">
                                    {{ $business->subscription?->periodEndsAt()?->format('d/m/Y') ?? '—' }}
                                </td>
                                {{-- Zero is not the same sentence as nothing owed: a dash
                                says there is nothing to collect, not that it measured 0. --}}
                                <td class="font-mono" data-label="{{ __('admin.businesses.owes') }}">
                                    {{ $business->owes() > 0 ? 'USD '.number_format($business->owes(), 2, ',', '.') : '—' }}
                                </td>
                                <td data-label="">
                                    <x-ui.button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="toggle({{ $business->id }})"
                                        data-testid="biz-open-{{ $business->id }}"
                                        data-row-action
                                    >{{ $this->open === $business->id
                                        ? __('admin.businesses.close')
                                        : __('admin.businesses.open') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.load-more :shown="$this->businesses->count()" :total="$this->matching" />
        @endif
    </x-ui.card>

    @if ($this->ficha !== null)
        @php($subscription = $this->ficha->subscription)

        <x-ui.card class="bp-card split-detail" data-testid="biz-ficha">
            <div class="bp-card-head">
                <h2>{{ $this->ficha->name }}</h2>

                {{-- Where a decision about THIS business is taken, so its history is one
                click from here and not a search on another screen. --}}
                @can('compliance.view')
                    <span class="bp-head-action">
                        <x-ui.button
                            variant="secondary"
                            size="sm"
                            icon="shield-alert"
                            :href="route('admin.compliance', ['negocio' => $this->ficha->id])"
                            wire:navigate
                            data-testid="biz-history-{{ $this->ficha->id }}"
                        >{{ trans_choice('admin.businesses.history', $this->historyCount, ['count' => $this->historyCount]) }}</x-ui.button>
                    </span>
                @endcan

                <x-ui.icon-button
                    icon="x"
                    variant="ghost"
                    size="sm"
                    :label="__('admin.businesses.close')"
                    wire:click="toggle({{ $this->ficha->id }})"
                    class="split-detail-close"
                    data-testid="biz-close-{{ $this->ficha->id }}"
                />
            </div>

            <div class="stat-grid stat-grid-fill">
                <x-ui.stat-card
                    :label="__('admin.businesses.card.plan')"
                    :value="$subscription ? __('plan.names.'.$subscription->plan) : '—'"
                    icon="gem"
                    tint="brand"
                >{{ $subscription
                    ? ($subscription->billing_cycle === 'yearly'
                        ? __('admin.businesses.card.yearly')
                        : __('admin.businesses.card.monthly'))
                    : __('admin.businesses.states.none') }}</x-ui.stat-card>

                <x-ui.stat-card
                    :label="__('admin.businesses.card.next_amount')"
                    :value="$subscription ? 'USD '.number_format($subscription->nextAmount(), 2, ',', '.') : '—'"
                    icon="receipt"
                    tint="info"
                >{{ __('admin.businesses.card.next_date') }}:
                    {{ $subscription?->periodEndsAt()?->format('d/m/Y') ?? '—' }}</x-ui.stat-card>

                <x-ui.stat-card
                    :label="__('admin.businesses.owes')"
                    :value="$this->ficha->owes() > 0 ? 'USD '.number_format($this->ficha->owes(), 2, ',', '.') : '—'"
                    icon="alert-triangle"
                    :tint="$this->ficha->owes() > 0 ? 'warning' : 'brand'"
                >{{ __('admin.businesses.states.'.$this->stateOf($this->ficha)) }}</x-ui.stat-card>
            </div>

            @if ($this->ficha->trashed())
                @can('businesses.manage')
                    <div class="bp-card-head mt-3"><h2>{{ __('admin.businesses.restore.title') }}</h2></div>
                    <p class="bp-card-sub">{{ __('admin.businesses.restore.hint', ['date' => $this->ficha->deleted_at?->format('d/m/Y')]) }}</p>
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        x-on:click="confirmRestore({{ $this->ficha->id }}, {{ Illuminate\Support\Js::from($this->ficha->name) }})"
                        data-testid="biz-restore-{{ $this->ficha->id }}"
                    >{{ __('admin.businesses.restore.action') }}</x-ui.button>
                @endcan
            @else
            <div class="bp-card-head mt-3"><h2>{{ __('admin.businesses.cancel.title') }}</h2></div>
            @if ($subscription === null)
                <p class="text-muted text-sm">{{ __('admin.businesses.cancel.no_subscription') }}</p>
            @elseif ($subscription->hasEnded())
                <p class="text-muted text-sm">
                    {{ __('admin.businesses.cancel.ended_on', ['date' => $subscription->periodEndsAt()?->format('d/m/Y')]) }}
                </p>
            @elseif ($subscription->isCanceling())
                <p class="bp-card-sub">
                    {{ __('admin.businesses.cancel.asked_on', ['date' => $subscription->canceled_at?->format('d/m/Y')]) }}
                    {{ __('admin.businesses.cancel.ends_on', ['date' => $subscription->periodEndsAt()?->format('d/m/Y')]) }}
                </p>
                <x-ui.button
                    variant="secondary"
                    size="sm"
                    x-on:click="confirmUndo({{ $this->ficha->id }}, {{ Illuminate\Support\Js::from($this->ficha->name) }})"
                    data-testid="biz-undo-{{ $this->ficha->id }}"
                >{{ __('admin.businesses.cancel.undo') }}</x-ui.button>
            @else
                <p class="bp-card-sub">
                    {{ $subscription->periodEndsAt()
                        ? __('admin.businesses.cancel.hint', ['date' => $subscription->periodEndsAt()->format('d/m/Y')])
                        : __('admin.businesses.cancel.hint_no_date') }}
                </p>
                <x-ui.button
                    variant="danger"
                    size="sm"
                    x-on:click="confirmCancel({{ $this->ficha->id }}, {{ Illuminate\Support\Js::from($this->ficha->name) }}, {{ Illuminate\Support\Js::from($subscription->periodEndsAt()?->format('d/m/Y')) }})"
                    data-testid="biz-cancel-{{ $this->ficha->id }}"
                >{{ __('admin.businesses.cancel.action') }}</x-ui.button>
            @endif

            @can('businesses.manage')
                <div class="bp-card-head mt-3"><h2>{{ __('admin.businesses.remove.title') }}</h2></div>
                <p class="bp-card-sub" data-testid="biz-frees-{{ $this->ficha->id }}">{{ $this->frees }} {{ __('admin.businesses.remove.hint') }}</p>
                <x-ui.button
                    variant="danger"
                    size="sm"
                    x-on:click="confirmRemove({{ $this->ficha->id }}, {{ Illuminate\Support\Js::from($this->ficha->name) }}, {{ Illuminate\Support\Js::from($this->frees) }})"
                    data-testid="biz-remove-{{ $this->ficha->id }}"
                >{{ __('admin.businesses.remove.action') }}</x-ui.button>
            @endcan
            @endif

            <div class="bp-card-head mt-3"><h2>{{ __('admin.businesses.card.payments') }}</h2></div>
            @if ($this->ficha->payments->isEmpty())
                <p class="text-muted text-sm">{{ __('admin.businesses.card.payments_empty') }}</p>
            @else
                <div class="pay-table-wrap">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.businesses.card.date') }}</th>
                                <th>{{ __('admin.businesses.card.concept') }}</th>
                                <th>{{ __('admin.businesses.card.amount') }}</th>
                                <th>{{ __('admin.businesses.card.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->ficha->payments as $payment)
                                <tr wire:key="biz-pay-{{ $payment->id }}">
                                    <td class="font-mono" data-label="{{ __('admin.businesses.card.date') }}">{{ $payment->created_at?->format('d/m/Y') }}</td>
                                    <td data-label="{{ __('admin.businesses.card.concept') }}">{{ __('plan.names.'.$payment->plan) }}</td>
                                    <td class="font-mono" data-label="{{ __('admin.businesses.card.amount') }}">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                    <td data-label="{{ __('admin.businesses.card.status') }}">
                                        <span @class([
                                            'status-tag',
                                            'is-danger' => $payment->status === App\Enums\PaymentStatus::Rejected,
                                        ])>{{ __('billing.history.statuses.'.$payment->status->value) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif
    </div>

    <x-ui.key-hints>
        <kbd class="cmdk-kbd">/</kbd> {{ __('admin.keys.search') }}
        <kbd class="cmdk-kbd">c</kbd> {{ __('admin.keys.clear') }}
    </x-ui.key-hints>
</div>

@script
    <script>
        // A baja stops money coming in and an undo starts it again: both ask first.
        Alpine.data('adminBusinesses', () => ({
            async confirmCancel(id, name, date) {
                const message = date
                    ? @js(__('admin.businesses.cancel.confirm')).replace(':name', name).replace(':date', date)
                    : @js(__('admin.businesses.cancel.confirm_no_date')).replace(':name', name);

                if (!(await dialog.confirm({
                    title: @js(__('admin.businesses.cancel.title')),
                    message,
                    accept: @js(__('admin.businesses.cancel.accept')),
                    type: 'danger',
                }))) {
                    return;
                }

                await this.$wire.cancel(id);
            },

            async confirmRestore(id, name) {
                if (!(await dialog.confirm({
                    title: @js(__('admin.businesses.restore.title')),
                    message: @js(__('admin.businesses.restore.confirm')).replace(':name', name),
                    accept: @js(__('admin.businesses.restore.accept')),
                    type: 'warning',
                }))) {
                    return;
                }

                await this.$wire.restore(id);
            },

            async confirmRelease(instance) {
                if (!(await dialog.confirm({
                    title: @js(__('admin.businesses.orphans.title')),
                    message: @js(__('admin.businesses.orphans.confirm')).replace(':instance', instance),
                    accept: @js(__('admin.businesses.orphans.accept')),
                    type: 'danger',
                }))) {
                    return;
                }

                await this.$wire.releaseOrphan(instance);
            },

            async confirmRemove(id, name, frees) {
                if (!(await dialog.confirm({
                    title: @js(__('admin.businesses.remove.title')),
                    message: @js(__('admin.businesses.remove.confirm')).replace(':name', name).replace(':frees', frees),
                    accept: @js(__('admin.businesses.remove.accept')),
                    type: 'danger',
                }))) {
                    return;
                }

                await this.$wire.remove(id);
            },

            async confirmUndo(id, name) {
                if (!(await dialog.confirm({
                    title: @js(__('admin.businesses.cancel.undo')),
                    message: @js(__('admin.businesses.cancel.undo_confirm')).replace(':name', name),
                    accept: @js(__('admin.businesses.cancel.undo')),
                    type: 'warning',
                }))) {
                    return;
                }

                await this.$wire.undoCancel(id);
            },
        }));
    </script>
@endscript
