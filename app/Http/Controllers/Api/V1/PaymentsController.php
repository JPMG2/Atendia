<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mis pagos" for the app: the running period, the next payment and the
 * business's OWN payments, read from the same `Billing` piece as the web.
 * The business comes from the token, never from the phone. Read only: the
 * receipt upload stays on the web (paying a subscription outside the stores
 * from inside the app is what Google Play and Apple police).
 */
class PaymentsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $billing = Client::for($user)->billing;
        $business = $user->business;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($billing === null || $business === null, 404);

        $showsBolivares = $business->livesInBolivares();
        $rate = $showsBolivares ? ExchangeRate::charging() : null;
        $period = $billing->period;

        return response()->json(['data' => [
            'title' => __('billing.title'),
            'sub' => __('billing.sub'),
            // Only a business that lives in bolivars is offered the toggle, as on the web.
            'money' => $showsBolivares ? [
                'label' => __('exchange_rate.toggle.label'),
                'usd' => __('exchange_rate.toggle.usd'),
                'ves' => __('exchange_rate.toggle.ves'),
            ] : null,
            'next' => $period === null ? null : [
                'title' => __('billing.next.title'),
                'due' => __('billing.next.due', ['date' => $period['ends_at']?->inBusinessTime()->format('d/m/Y')]),
                'line' => __('billing.next.line', ['plan' => $period['plan_name'], 'cycle' => __('billing.next.cycle_'.$period['cycle'])]),
                'amount' => $period['amount'],
                'amount_ves' => $rate === null ? null : ExchangeRate::formatBolivares($rate->toBolivares($period['dollars'])),
                'rate_note' => $showsBolivares
                    ? ($rate === null ? __('exchange_rate.toggle.no_rate') : __('exchange_rate.toggle.today', ['date' => $rate->rate_date->format('d/m/Y')]))
                    : null,
            ],
            'history' => [
                'title' => __('billing.history.title'),
                'sub' => __('billing.history.sub'),
                'empty' => __('billing.history.empty'),
                'labels' => [
                    'date' => __('billing.history.date'),
                    'period' => __('billing.history.period'),
                    'amount' => __('billing.history.amount'),
                    'method' => __('billing.history.method'),
                ],
                'rows' => $billing->history->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'date' => $payment->created_at->inBusinessTime()->format('d/m/Y'),
                    'concept' => __('billing.history.concept_line', ['plan' => __('plan.names.'.$payment->plan)]),
                    'period' => $payment->period_starts_at
                        ? $payment->period_starts_at->inBusinessTime()->format('d/m').' – '.$payment->period_ends_at?->inBusinessTime()->format('d/m/Y')
                        : '—',
                    'amount' => $payment->formattedAmount(),
                    'amount_ves' => $payment->formattedBolivares(),
                    'rate_note' => $payment->formattedBolivares() === null
                        ? __('exchange_rate.toggle.no_rate')
                        : __('exchange_rate.toggle.paid_at', ['date' => $payment->exchange_rate_on?->format('d/m/Y')]),
                    'method' => __('billing.history.methods.'.$payment->method),
                    'status' => __('billing.history.statuses.'.$payment->status->value),
                    'tone' => match ($payment->status) {
                        PaymentStatus::Paid => 'brand',
                        PaymentStatus::Pending => 'warning',
                        PaymentStatus::Rejected => 'danger',
                    },
                    'reason' => $payment->rejection_reason,
                ])->values()->all(),
            ],
        ]]);
    }
}
