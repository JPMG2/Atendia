<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Billing\SubmitPaymentReceipt;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * The billing piece: what the business owes Atendia and what it paid.
 * Payment-rail agnostic — today a receipt the admin verifies — so the
 * screens never learn how the money actually moves.
 */
class Billing
{
    public function __construct(private Business $business) {}

    public ?Subscription $subscription {
        get => $this->business->subscription;
    }

    /**
     * The running period as the screen draws it.
     *
     * @var array{plan: string, plan_name: string, cycle: string, status: SubscriptionStatus, starts_at: CarbonInterface|null, ends_at: CarbonInterface|null, length: int, used: int, days_left: int|null, amount: string}|null
     */
    public ?array $period {
        get {
            $subscription = $this->subscription;

            if ($subscription === null || $subscription->periodEndsAt() === null) {
                return null;
            }

            return [
                'plan' => $subscription->plan,
                'plan_name' => __('plan.names.'.$subscription->plan),
                'cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'starts_at' => $subscription->periodStartsAt(),
                'ends_at' => $subscription->periodEndsAt(),
                'length' => $subscription->periodLengthDays(),
                'used' => $subscription->daysUsed(),
                'days_left' => $subscription->daysUntilPayment(),
                'amount' => config('atendia.billing.currency').' '.number_format($subscription->nextAmount(), 2, ',', '.'),
            ];
        }
    }

    /**
     * What the panel banner and the menu badge say, or null when nothing is
     * due soon. A receipt under review outranks every warning: they paid.
     *
     * @var array{stage: string, days: int}|null
     */
    public ?array $reminder {
        get {
            $subscription = $this->subscription;
            $days = $subscription?->daysUntilPayment();

            if ($subscription === null || $days === null) {
                return null;
            }

            if ($this->pending !== null) {
                return ['stage' => 'verifying', 'days' => $days];
            }

            if ($subscription->isPaused()) {
                return ['stage' => 'paused', 'days' => 0];
            }

            if ($days <= 0) {
                return ['stage' => 'overdue', 'days' => max(0, (int) config('atendia.billing.grace_days') + $days)];
            }

            return $days <= max((array) config('atendia.billing.reminder_days'))
                ? ['stage' => 'upcoming', 'days' => $days]
                : null;
        }
    }

    /** @var Collection<int, Payment> */
    public Collection $history {
        get => $this->business->payments()->latest('id')->get();
    }

    public ?Payment $pending {
        get => $this->business->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();
    }

    public function submitReceipt(UploadedFile $receipt, ?string $reference): Payment
    {
        return app(SubmitPaymentReceipt::class)->handle($this->business, $receipt, $reference);
    }
}
