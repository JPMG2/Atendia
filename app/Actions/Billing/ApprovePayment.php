<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\BillingPaymentReviewed;
use App\Messaging\Channels\Email;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin credits a payment: the subscription gets its next period and,
 * if the assistant was paused for lack of payment, it answers again.
 */
class ApprovePayment
{
    public function handle(Payment $payment, User $reviewer): Payment
    {
        DB::transaction(function () use ($payment, $reviewer): void {
            $subscription = $payment->subscription;
            $end = $subscription?->periodEndsAt();

            // A period still running continues from its end; a lapsed one
            // (grace or pause) starts today, never charging for days unused.
            $start = $end !== null && $end->isFuture() ? $end->copy() : now();
            $periodEnd = $payment->billing_cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'period_starts_at' => $start,
                'period_ends_at' => $periodEnd,
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => null,
            ])->save();

            $subscription?->forceFill([
                'plan' => $payment->plan,
                'billing_cycle' => $payment->billing_cycle,
                'status' => SubscriptionStatus::Active,
                'current_period_ends_at' => $periodEnd,
                'paused_at' => null,
            ])->save();
        });

        $this->mailVerdict($payment);

        return $payment;
    }

    private function mailVerdict(Payment $payment): void
    {
        $email = $payment->business?->billing_email;

        if (filled($email)) {
            (new Email($payment, [$email], BillingPaymentReviewed::class))->send();
        }
    }
}
