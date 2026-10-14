<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PlatformEventKind;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PlatformEvent;
use App\Models\Subscription;

/**
 * Makes the feed agree with what is true NOW for the business-level kinds.
 * Writers raise on a transition, so a condition that predates them has no
 * line and a missed webhook never closes one. A line already open is left
 * alone, so seen state and occurrence counts survive.
 */
class ReconcilePlatformEvents
{
    public function __construct(private readonly RaisePlatformEvent $raise, private readonly ResolvePlatformEvent $resolve) {}

    public function handle(): void
    {
        $real = Business::query()->where('is_demo', false);

        $this->align(PlatformEventKind::WhatsAppDisconnected, (clone $real)->whereNotNull('whatsapp_instance')->whereNull('whatsapp_connected_at')->pluck('id')->all());
        $this->align(PlatformEventKind::BusinessSuspended, (clone $real)->whereNotNull('suspended_at')->pluck('id')->all());
        $this->align(PlatformEventKind::PaymentLate, $this->businessesIn(SubscriptionStatus::PastDue));
        $this->align(PlatformEventKind::PaymentPaused, $this->businessesIn(SubscriptionStatus::Paused));

        $this->alignReceipts();
    }

    /** @return list<int> */
    private function businessesIn(SubscriptionStatus $status): array
    {
        return Subscription::query()
            ->where('status', $status->value)
            ->whereIn('business_id', Business::query()->where('is_demo', false)->select('id'))
            ->pluck('business_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $businessIds  Everyone who is in the condition right now.
     */
    private function align(PlatformEventKind $kind, array $businessIds): void
    {
        $open = PlatformEvent::query()->active()->where('kind', $kind->value)->pluck('about_business_id')->filter()->all();

        foreach (array_diff($businessIds, $open) as $id) {
            $this->raise->handle($kind, $id);
        }

        foreach (array_diff($open, $businessIds) as $id) {
            $this->resolve->ofKind($kind, $id);
        }
    }

    private function alignReceipts(): void
    {
        $waiting = Payment::query()->where('status', PaymentStatus::Pending->value)->get(['id', 'business_id']);
        $keys = $waiting->map(fn (Payment $payment): string => 'receipt_received:'.$payment->id)->all();

        $open = PlatformEvent::query()->active()->where('kind', PlatformEventKind::ReceiptReceived->value)->pluck('dedupe_key')->all();

        foreach ($waiting as $payment) {
            if (! in_array('receipt_received:'.$payment->id, $open, true)) {
                $this->raise->handle(PlatformEventKind::ReceiptReceived, $payment->business_id, null, 'receipt_received:'.$payment->id);
            }
        }

        foreach (array_diff($open, $keys) as $key) {
            if ($key !== null) {
                $this->resolve->byKey($key);
            }
        }
    }
}
