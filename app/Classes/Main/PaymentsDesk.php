<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * The payments desk as data, with the words it prints: the web screen and the mobile API both read
 * from here, so a row cannot say one thing on the panel and another on the phone. Crediting and
 * rejecting are actions and live where they are taken (`PaymentReviewForm`), not here.
 */
class PaymentsDesk
{
    public const int PAGE = 25;

    /** @param  int|null  $visible  receipts of the queue to load; null = all of them, as the web screen shows them */
    public function __construct(public readonly ?int $visible = null) {}

    /** @var int receipts waiting for the admin, of which `visible` are loaded */
    public int $pendingTotal {
        get => $this->pendingTotal ??= Payment::pendingReviewCount();
    }

    /** @var Collection<int, Payment> */
    public Collection $queue {
        get => $this->queue ??= Payment::reviewQueue($this->visible);
    }

    /** @var Collection<int, Payment> */
    public Collection $recent {
        get => $this->recent ??= Payment::recentlyReviewed();
    }

    public bool $hasMore {
        get => $this->queue->count() < $this->pendingTotal;
    }

    /** @var list<array{id: int, date: string, business: string, concept: string, amount: string, reference: string, receipt: bool}> */
    public array $pendingRows {
        get => $this->queue->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            // The web links the file; the app has no download yet, so the API leaves this out.
            'receipt' => (bool) $payment->receipt_path,
            'date' => $payment->created_at->format('d/m/Y H:i'),
            'business' => (string) $payment->business?->name,
            'concept' => __('billing.history.concept_line', ['plan' => __('plan.names.'.$payment->plan)]),
            'amount' => $payment->formattedAmount(),
            'reference' => $payment->reference ?? '—',
        ])->all();
    }

    /** @var list<array{id: int, date: string, business: string, amount: string, status: string, tone: string, reason: string|null}> */
    public array $recentRows {
        get => $this->recent->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            'date' => $payment->updated_at->format('d/m/Y'),
            'business' => (string) $payment->business?->name,
            'amount' => $payment->formattedAmount(),
            'status' => __('billing.history.statuses.'.$payment->status->value),
            'tone' => match ($payment->status) {
                PaymentStatus::Paid => 'brand',
                PaymentStatus::Rejected => 'danger',
                default => 'neutral',
            },
            'reason' => $payment->rejection_reason,
        ])->all();
    }

    /** @var array{date: string, business: string, concept: string, amount: string, reference: string} */
    public array $labels {
        get => [
            'date' => __('billing.history.date'),
            'business' => __('billing.admin.business'),
            'concept' => __('billing.history.concept'),
            'amount' => __('billing.history.amount'),
            'reference' => __('billing.admin.reference'),
        ];
    }
}
