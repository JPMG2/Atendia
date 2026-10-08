<?php

use App\Classes\Main\Billing;
use App\Classes\Main\Client;
use App\Enums\SubscriptionStatus;
use App\Livewire\Forms\Billing\PaymentReceiptForm;
use App\Models\Business;
use App\Models\Company;
use App\Models\Payment;
use App\Traits\HasNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Mis pagos": the running period, the next payment and every payment made
 * to Atendia. Everything is read from the Billing piece; the receipt form
 * only uploads, the admin credits.
 */
new class extends Component
{
    use HasNotifications;
    use WithFileUploads;

    public PaymentReceiptForm $form;

    public bool $uploading = false;

    /** Empty = every year: with two years loaded the history is already long. */
    public string $year = '';

    #[Computed]
    public function billing(): ?Billing
    {
        return Client::for(Auth::user())->billing;
    }

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()->business;
    }

    #[Computed]
    public function instructions(): ?string
    {
        return Company::paymentInstructions();
    }

    /**
     * Every payment, read once. The year filter works on THIS collection: the
     * rows are few and the year has to be the business's, not UTC — a payment
     * made at 22:00 on 31/12 in Buenos Aires belongs to the year it shows.
     *
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->billing?->history ?? new Collection;
    }

    /**
     * Newest first, for the picker.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function years(): array
    {
        return $this->history
            ->map(fn (Payment $payment): int => $payment->created_at->inBusinessTime()->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
            ->all();
    }

    /** @return Collection<int, Payment> */
    #[Computed]
    public function visibleHistory(): Collection
    {
        return $this->year === ''
            ? $this->history
            : $this->history->filter(
                fn (Payment $payment): bool => (string) $payment->created_at->inBusinessTime()->year === $this->year
            )->values();
    }

    public function openUpload(): void
    {
        $this->form->setup();
        $this->resetErrorBag();
        $this->uploading = true;
    }

    public function closeUpload(): void
    {
        $this->uploading = false;
    }

    public function submit(): void
    {
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type === App\Enums\NotificationType::Success) {
            $this->uploading = false;
            unset($this->billing);
        }
    }
};
?>

<div>
    <x-ui.page-head :title="__('billing.title')" :sub="__('billing.sub')" />

    @php
        $period = $this->billing?->period;
        $onTrial = $period !== null && $period['status'] === SubscriptionStatus::Trialing;
        $percent = $period === null ? 0 : (int) round($period['used'] / max(1, $period['length']) * 100);
        $late = $period !== null && ($period['days_left'] ?? 1) <= 5;
    @endphp

    @if ($period !== null)
        <div class="pay-grid">
            <x-ui.card class="bp-card">
                <div class="bp-card-head"><h2>{{ __('billing.period.title') }}</h2></div>
                <p class="bp-card-sub">
                    {{ $onTrial ? __('billing.period.trial', ['plan' => $period['plan_name']]) : __('billing.period.'.$period['cycle'], ['plan' => $period['plan_name']]) }}
                </p>

                <div class="pay-cycle">
                    <div
                        class="pay-ring"
                        role="img"
                        aria-label="{{ $period['used'] }} {{ __('billing.period.of_days', ['days' => $period['length']]) }}"
                    >
                        <svg viewBox="0 0 44 44">
                            <circle cx="22" cy="22" r="19" fill="none" stroke="var(--border-subtle)" stroke-width="4" />
                            <circle
                                cx="22"
                                cy="22"
                                r="19"
                                fill="none"
                                stroke="{{ $late ? 'var(--warning)' : 'var(--brand)' }}"
                                stroke-width="4"
                                stroke-linecap="round"
                                pathLength="100"
                                stroke-dasharray="{{ $percent }} 100"
                            />
                        </svg>
                        <div class="pay-ring-label">
                            <b class="font-mono">{{ $period['used'] }}</b>
                            <small>{{ __('billing.period.of_days', ['days' => $period['length']]) }}</small>
                        </div>
                    </div>

                    <dl class="pay-facts">
                        <div>
                            <dt>{{ __('billing.period.started') }}</dt>
                            <dd class="font-mono">{{ $period['starts_at']?->inBusinessTime()->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt>{{ $onTrial ? __('billing.period.trial_ends') : __('billing.period.renews') }}</dt>
                            <dd class="font-mono">{{ $period['ends_at']?->inBusinessTime()->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('billing.period.used') }}</dt>
                            <dd class="font-mono">{{ $period['used'] }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('billing.period.left') }}</dt>
                            <dd class="font-mono">
                                @if (($period['days_left'] ?? 0) > 0)
                                    {{ trans_choice('billing.period.left_days', $period['days_left']) }}
                                @else
                                    {{ __('billing.period.overdue') }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="pay-bar" aria-hidden="true">
                    <i @class(['is-late' => $late]) style="width: {{ $percent }}%"></i>
                </div>
            </x-ui.card>

            <x-ui.card class="bp-card pay-next">
                <div>
                    <div class="bp-card-head"><h2>{{ __('billing.next.title') }}</h2></div>
                    <p class="bp-card-sub">
                        {{ __('billing.next.due', ['date' => $period['ends_at']?->inBusinessTime()->format('d/m/Y')]) }}
                    </p>
                </div>
                <b class="pay-amount font-mono">{{ $period['amount'] }}</b>
                <div class="pay-line">
                    <span>{{ __('billing.next.line', ['plan' => $period['plan_name'], 'cycle' => __('billing.next.cycle_'.$period['cycle'])]) }}</span>
                    <b class="font-mono">{{ $period['amount'] }}</b>
                </div>

                <div class="pay-howto">
                    <p class="pay-howto-title">{{ __('billing.next.how') }}</p>
                    @if (filled($this->instructions))
                        <p class="pay-howto-body">{{ $this->instructions }}</p>
                    @else
                        <p class="pay-howto-body text-muted">{{ __('billing.next.no_instructions') }}</p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="primary" size="sm" icon="upload" wire:click="openUpload">
                        {{ __('billing.next.upload') }}</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif

    <x-ui.card class="bp-card mt-3">
        <div class="bp-card-head"><h2>{{ __('billing.history.title') }}</h2></div>
        <p class="bp-card-sub">{{ __('billing.history.sub') }}</p>

        @if ($this->history->isEmpty())
            <p class="text-muted text-sm">{{ __('billing.history.empty') }}</p>
        @else
            {{-- A declared row, as every toolbar in the panel: the picker keeps
            its own width and the row still reaches the right edge. --}}
            @if (count($this->years) > 1)
                <x-catalog.form-row>
                    <x-inputsform.combobox
                        span="short"
                        name="history_year"
                        wire:model.live="year"
                        :value="$year"
                        :placeholder="__('billing.history.all_years')"
                        :options="$this->years"
                    />
                </x-catalog.form-row>
            @endif

            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('billing.history.date') }}</th>
                            <th>{{ __('billing.history.concept') }}</th>
                            <th>{{ __('billing.history.period') }}</th>
                            <th>{{ __('billing.history.amount') }}</th>
                            <th>{{ __('billing.history.method') }}</th>
                            <th>{{ __('billing.history.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->visibleHistory as $payment)
                            <tr wire:key="payment-{{ $payment->id }}">
                                {{-- data-label: on a phone the table stacks and each
                                cell carries its own heading, because a 7-column
                                table there either scrolls sideways or gets cut. --}}
                                <td class="font-mono" data-label="{{ __('billing.history.date') }}">{{ $payment->created_at->inBusinessTime()->format('d/m/Y') }}</td>
                                <td class="is-key" data-label="{{ __('billing.history.concept') }}">
                                    {{ __('billing.history.concept_line', ['plan' => __('plan.names.'.$payment->plan)]) }}
                                </td>
                                <td class="font-mono" data-label="{{ __('billing.history.period') }}">
                                    @if ($payment->period_starts_at)
                                        {{ $payment->period_starts_at->inBusinessTime()->format('d/m') }} – {{ $payment->period_ends_at?->inBusinessTime()->format('d/m/Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="font-mono" data-label="{{ __('billing.history.amount') }}">{{ $payment->formattedAmount() }}</td>
                                <td data-label="{{ __('billing.history.method') }}">{{ __('billing.history.methods.'.$payment->method) }}</td>
                                <td data-label="{{ __('billing.history.status') }}">
                                    <span
                                        @class([
                                            'status-tag',
                                            'is-brand' => $payment->status === App\Enums\PaymentStatus::Paid,
                                            'is-warning' => $payment->status === App\Enums\PaymentStatus::Pending,
                                            'is-danger' => $payment->status === App\Enums\PaymentStatus::Rejected,
                                        ])
                                    >{{ __('billing.history.statuses.'.$payment->status->value) }}</span>
                                    @if ($payment->rejection_reason)
                                        <span class="text-muted block text-xs">{{ $payment->rejection_reason }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payment->receipt_path)
                                        <a
                                            class="st-link"
                                            href="{{ route('my-payments.receipt', $payment) }}"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            {{ __('billing.history.receipt') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <div class="pay-grid mt-3">
        <x-ui.card class="bp-card">
            <div class="bp-card-head"><h2>{{ __('billing.billing_data.title') }}</h2></div>
            <dl class="pay-kv">
                <dt>{{ __('billing.billing_data.business') }}</dt>
                <dd>{{ $this->business?->name }}</dd>
                <dt>{{ __('billing.billing_data.tax_id') }}</dt>
                <dd class="font-mono">{{ $this->business?->tax_id ?? __('billing.billing_data.missing') }}</dd>
                <dt>{{ __('billing.billing_data.tax_condition') }}</dt>
                <dd>{{ $this->business?->taxCondition?->name ?? __('billing.billing_data.missing') }}</dd>
                <dt>{{ __('billing.billing_data.email') }}</dt>
                <dd class="font-mono">{{ $this->business?->billing_email }}</dd>
            </dl>
            <a
                href="{{ route('my-business.facturacion') }}"
                wire:navigate
                class="st-link"
            >{{ __('billing.billing_data.edit') }}</a>
        </x-ui.card>

        @if ($period !== null && $period['cycle'] === 'monthly')
            @php($periodPlan = \App\Classes\Main\Plan::named($period['plan']))
            <x-ui.card class="bp-card">
                <div class="bp-card-head"><h2>{{ __('billing.yearly.title') }}</h2></div>
                <p class="bp-card-sub">
                    {{ __('billing.yearly.body', ['amount' => config('atendia.billing.currency').' '.number_format($periodPlan->annualSavings, 0, ',', '.')]) }}
                </p>
                <p class="pay-amount pay-amount-sm font-mono">
                    {{ config('atendia.billing.currency') }} {{ $periodPlan->annualMonthlyPrice }}
                    <small class="text-muted">{{ __('billing.yearly.per_month') }}</small>
                </p>
                <x-ui.button
                    variant="secondary"
                    size="sm"
                    :href="route('my-plan')"
                >
                    {{ __('billing.yearly.cta') }}</x-ui.button>
            </x-ui.card>
        @endif
    </div>

    @if ($uploading)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeUpload()"
            :title="__('billing.receipt.title')"
            :subtitle="__('billing.receipt.sub')"
        >
            <div class="flex flex-col gap-4">
                @if ($period !== null)
                    <div class="pay-line">
                        <span>{{ __('billing.receipt.amount') }}</span>
                        <b class="font-mono">{{ $period['amount'] }}</b>
                    </div>
                @endif
                <x-catalog.form-row>
                    <x-inputsform.file
                        span="full"
                        name="receipt"
                        accept="image/png,image/webp,image/jpeg"
                        :label="__('billing.receipt.file')"
                        :note="__('billing.receipt.file_note')"
                        wire:model="form.receipt"
                    />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input
                        span="full"
                        name="reference"
                        class="font-mono"
                        maxlength="60"
                        :label="__('billing.receipt.reference')"
                        wire:model="form.reference"
                    />
                </x-catalog.form-row>
            </div>

            <x-slot:footer>
                <x-ui.button
                    variant="danger"
                    size="sm"
                    wire:click="closeUpload"
                >
                    {{ __('billing.receipt.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" wire:click="submit" wire:loading.attr="disabled">
                    {{ __('billing.receipt.submit') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
