<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\BillingPaymentReviewed;
use App\Mail\FirstPaymentReceived;
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
        // Read BEFORE the transaction writes Active over it: a renewal and a
        // first payment look identical afterwards, and only one is news.
        $wasTrialing = $payment->subscription?->status === SubscriptionStatus::Trialing;

        DB::transaction(function () use ($payment, $reviewer): void {
            $subscription = $payment->subscription;
            $end = $subscription?->periodEndsAt();

            // A period still running continues from its end; a lapsed one
            // (grace or pause) starts today, never charging for days unused.
            $start = $end !== null && $end->isFuture() ? $end->copy() : now();
            // NoOverflow: approved on the 31st, addMonth() landed on the 3rd and
            // dragged every renewal after it.
            $periodEnd = $payment->billing_cycle === 'yearly' ? $start->copy()->addYearNoOverflow() : $start->copy()->addMonthNoOverflow();

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

        if ($wasTrialing) {
            $this->mailConversion($payment);
        }

        return $payment;
    }

    /**
     * Only the FIRST payment reaches her. Every renewal would be noise, and
     * noise is how a mailbox teaches somebody to archive without reading.
     */
    private function mailConversion(Payment $payment): void
    {
        $admin = User::where('email', (string) config('atendia.admin_email'))->first();

        if ($admin !== null) {
            (new Email($payment, [(string) $admin->email], FirstPaymentReceived::class))->send();
        }
    }

    private function mailVerdict(Payment $payment): void
    {
        $email = $payment->business?->billing_email;

        if (filled($email)) {
            (new Email($payment, [$email], BillingPaymentReviewed::class))->send();
        }
    }
}
