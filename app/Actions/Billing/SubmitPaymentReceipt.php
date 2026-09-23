<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Business;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;

/**
 * The client paid by transfer and shows the receipt: the payment waits as
 * pending until the admin verifies it. The file lives on the PRIVATE disk
 * under the tenant's folder and is only served back re-checking its owner.
 */
class SubmitPaymentReceipt
{
    public function handle(Business $business, UploadedFile $receipt, ?string $reference): Payment
    {
        $subscription = $business->subscription;

        return $business->payments()->create([
            'subscription_id' => $subscription?->id,
            'plan' => $subscription?->plan ?? (string) config('atendia.trial.plan'),
            'billing_cycle' => $subscription?->billing_cycle ?? 'monthly',
            'amount' => $subscription?->nextAmount() ?? 0,
            'currency' => (string) config('atendia.billing.currency'),
            'method' => 'transfer',
            'reference' => $reference,
            'receipt_path' => $receipt->store("businesses/{$business->id}/payments", 'local'),
        ]);
    }
}
