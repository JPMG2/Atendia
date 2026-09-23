<?php

use App\Livewire\Forms\Admin\PaymentReviewForm;
use App\Models\Payment;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The payments desk: transfer receipts waiting to be credited, and the
 * latest reviewed. Crediting extends the client's period and wakes a
 * paused assistant; rejecting tells the client why.
 */
new class extends Component
{
    use HasNotifications;

    public PaymentReviewForm $form;

    /** @return Collection<int, Payment> */
    #[Computed]
    public function queue(): Collection
    {
        return Payment::reviewQueue();
    }

    /** @return Collection<int, Payment> */
    #[Computed]
    public function recent(): Collection
    {
        return Payment::recentlyReviewed();
    }

    public function approve(int $id): void
    {
        $this->dispatchNotification($this->form->approve($id));
        unset($this->queue, $this->recent);
    }

    public function startReject(int $id): void
    {
        $this->form->setup();
        $this->form->rejectingId = $id;
    }

    public function cancelReject(): void
    {
        $this->form->setup();
        $this->resetErrorBag();
    }

    public function reject(): void
    {
        $this->dispatchNotification($this->form->reject());
        unset($this->queue, $this->recent);
    }

    public function render(): View
    {
        return $this->view()->title(__('billing.admin.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('billing.admin.title') }}</h1>
            <p class="page-head-sub">{{ __('billing.admin.sub') }}</p>
        </div>
    </div>

    <x-ui.card class="bp-card" x-data="adminPayments">
        <div class="bp-card-head"><h2>{{ __('billing.admin.pending') }}</h2></div>

        @if ($this->queue->isEmpty())
            <p class="text-muted text-sm">{{ __('billing.admin.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('billing.history.date') }}</th>
                            <th>{{ __('billing.admin.business') }}</th>
                            <th>{{ __('billing.history.concept') }}</th>
                            <th>{{ __('billing.history.amount') }}</th>
                            <th>{{ __('billing.admin.reference') }}</th>
                            <th>{{ __('billing.history.receipt') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->queue as $payment)
                            <tr wire:key="pending-{{ $payment->id }}">
                                <td class="font-mono">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $payment->business?->name }}</td>
                                <td>{{ __('billing.history.concept_line', ['plan' => __('plan.names.'.$payment->plan)]) }}</td>
                                <td class="font-mono">{{ $payment->formattedAmount() }}</td>
                                <td class="font-mono">{{ $payment->reference ?? '—' }}</td>
                                <td>
                                    @if ($payment->receipt_path)
                                        <a class="st-link" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" rel="noopener">
                                            {{ __('billing.history.receipt') }}</a>
                                    @endif
                                </td>
                                <td>
                                    @if ($form->rejectingId === $payment->id)
                                        <div class="flex min-w-[260px] flex-col gap-2">
                                            <x-catalog.form-row>
                                                <x-inputsform.input
                                                    span="full"
                                                    name="reason"
                                                    size="s"
                                                    :placeholder="__('billing.admin.reason_placeholder')"
                                                    wire:model="form.reason"
                                                />
                                            </x-catalog.form-row>
                                            <div class="flex gap-2">
                                                <x-ui.button variant="danger" size="sm" wire:click="cancelReject">
                                                    {{ __('billing.receipt.cancel') }}</x-ui.button>
                                                <x-ui.button variant="secondary" size="sm" wire:click="reject">
                                                    {{ __('billing.admin.reject') }}</x-ui.button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex gap-2">
                                            <x-ui.button
                                                variant="primary"
                                                size="sm"
                                                x-on:click="confirmApprove({{ $payment->id }}, {{ Illuminate\Support\Js::from($payment->business?->name ?? '') }})"
                                            >
                                                {{ __('billing.admin.approve') }}</x-ui.button>
                                            <x-ui.button variant="secondary" size="sm" wire:click="startReject({{ $payment->id }})">
                                                {{ __('billing.admin.reject') }}</x-ui.button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card class="bp-card mt-3">
        <div class="bp-card-head"><h2>{{ __('billing.admin.recent') }}</h2></div>
        @if ($this->recent->isEmpty())
            <p class="text-muted text-sm">{{ __('billing.admin.recent_empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <tbody>
                        @foreach ($this->recent as $payment)
                            <tr wire:key="recent-{{ $payment->id }}">
                                <td class="font-mono">{{ $payment->updated_at->format('d/m/Y') }}</td>
                                <td>{{ $payment->business?->name }}</td>
                                <td class="font-mono">{{ $payment->formattedAmount() }}</td>
                                <td>
                                    <span @class([
                                        'status-tag',
                                        'is-brand' => $payment->status === App\Enums\PaymentStatus::Paid,
                                        'is-danger' => $payment->status === App\Enums\PaymentStatus::Rejected,
                                    ])>{{ __('billing.history.statuses.'.$payment->status->value) }}</span>
                                </td>
                                <td class="text-muted">{{ $payment->rejection_reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>

@script
    <script>
        // Crediting moves money and wakes a paused assistant: one question first.
        Alpine.data('adminPayments', () => ({
            async confirmApprove(id, business) {
                if (
                    !(await dialog.confirm({
                        title: @js(__('billing.admin.approve_title')),
                        message: @js(__('billing.admin.approve_message')).replace(':business', business),
                        accept: @js(__('billing.admin.approve')),
                        type: 'info',
                    }))
                ) {
                    return;
                }

                await this.$wire.approve(id);
            },
        }));
    </script>
@endscript
