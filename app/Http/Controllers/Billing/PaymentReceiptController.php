<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a receipt from the PRIVATE disk. The tenant scope resolves the
 * payment, so a client only ever reaches its own; the admin, with no
 * tenant set, reaches any to verify it.
 */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        abort_if($payment->receipt_path === null || ! Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path);
    }
}
