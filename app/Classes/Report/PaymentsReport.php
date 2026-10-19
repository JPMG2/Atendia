<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Billing;
use App\Classes\Main\Client;
use App\Enums\ReportAudience;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** "Mis pagos": the business's payment history, one year of it or all, as the screen filters it. */
class PaymentsReport extends ListReport
{
    public string $title {
        get => __('reports/account.payments.title');
    }

    public string $filename {
        get => __('reports/account.payments.filename');
    }

    public array $columns {
        get => [
            __('reports/account.payments.date'),
            __('reports/account.payments.concept'),
            __('reports/account.payments.period'),
            __('reports/account.payments.amount'),
            __('reports/account.payments.currency'),
            __('reports/account.payments.method'),
            __('reports/account.payments.status'),
            __('reports/account.payments.reference'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->billing()?->history->count() ?? 0;
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->can('manage-business') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->payments($scope)
            ->map(fn (Payment $payment): array => [
                $payment->created_at->inBusinessTime()->format('d/m/Y'),
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
        return $this->payments($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        return $scope->value('anio') === '' ? [] : [['label' => __('reports/account.payments.filter_year'), 'value' => $scope->value('anio')]];
    }

    /** @return Collection<int, Payment> */
    private function payments(ReportScope $scope): Collection
    {
        return $this->billing()?->historyOf($scope->value('anio')) ?? new Collection;
    }

    private function billing(): ?Billing
    {
        return Client::for(auth()->user())->billing;
    }
}
