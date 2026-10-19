<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/** Every payment of every business, newest first: the ledger the accountant asks for. */
class PlatformPaymentsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.payments.title');
    }

    public string $filename {
        get => __('reports/admin.payments.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.payments.date'),
            __('reports/admin.payments.business'),
            __('reports/admin.payments.concept'),
            __('reports/admin.payments.period'),
            __('reports/admin.payments.amount'),
            __('reports/admin.payments.currency'),
            __('reports/admin.payments.method'),
            __('reports/admin.payments.status'),
            __('reports/admin.payments.reference'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->ledger()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('payments.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->ledger()
            ->map(fn (Payment $payment): array => [
                $payment->created_at->inBusinessTime()->format('d/m/Y'),
                (string) $payment->business?->name,
                __('billing.history.concept_line', ['plan' => __('plan.names.'.$payment->plan)]),
                $payment->period_starts_at
                    ? $payment->period_starts_at->inBusinessTime()->format('d/m/Y').' – '.$payment->period_ends_at?->inBusinessTime()->format('d/m/Y')
                    : '',
                (float) $payment->amount,
                (string) $payment->currency,
                __('billing.history.methods.'.$payment->method),
                __('billing.history.statuses.'.$payment->status->value),
                (string) $payment->reference,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->ledger()->count();
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** @return Collection<int, Payment> */
    private function ledger(): Collection
    {
        return Payment::ledger();
    }
}
