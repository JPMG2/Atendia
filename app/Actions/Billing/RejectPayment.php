<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Compliance\RecordComplianceEvent;
use App\Enums\ComplianceEventKind;
use App\Enums\PaymentStatus;
use App\Mail\BillingPaymentReviewed;
use App\Messaging\Channels\Email;
use App\Models\Payment;
use App\Models\User;

/** The receipt does not check out: the client reads why and sends another. */
class RejectPayment
{
    public function handle(Payment $payment, User $reviewer, string $reason): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
        ])->save();

        app(RecordComplianceEvent::class)->handle($payment->business_id, ComplianceEventKind::PaymentRejected, $reason, $reviewer->id);

        $email = $payment->business?->billing_email;

        if (filled($email)) {
            (new Email($payment, [$email], BillingPaymentReviewed::class))->send();
        }

        return $payment;
    }
}
