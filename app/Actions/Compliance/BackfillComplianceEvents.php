<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Enums\ComplianceEventKind;
use App\Enums\ModerationSeverity;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\ComplianceEvent;
use App\Models\ModerationFlag;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\CarbonInterface;

/**
 * Writes into the file what the platform knew before the file existed: only
 * what left a dated trace. A suspension lifted before this existed left
 * nothing, and nothing is invented for it. Safe to run twice: a row is keyed
 * by business, kind and moment.
 */
class BackfillComplianceEvents
{
    /** @return int How many lines were written. */
    public function handle(): int
    {
        $written = 0;

        ModerationFlag::query()->each(function (ModerationFlag $flag) use (&$written): void {
            $written += $this->write(
                $flag->business_id,
                $flag->severity === ModerationSeverity::Severe ? ComplianceEventKind::ContentSevere : ComplianceEventKind::ContentRejected,
                $flag->created_at,
                $flag->source.' · '.$flag->category,
            );
        });

        Payment::query()->where('status', PaymentStatus::Rejected)->each(function (Payment $payment) use (&$written): void {
            $written += $this->write($payment->business_id, ComplianceEventKind::PaymentRejected, $payment->updated_at, $payment->rejection_reason, $payment->reviewed_by);
        });

        Business::query()->whereNotNull('suspended_at')->each(function (Business $business) use (&$written): void {
            $written += $this->write($business->id, ComplianceEventKind::Suspended, $business->suspended_at, $business->suspension_reason);

            if ($business->appealed_at !== null) {
                $written += $this->write($business->id, ComplianceEventKind::Appealed, $business->appealed_at, $business->appeal_message);
            }
        });

        Subscription::query()
            ->whereIn('status', [SubscriptionStatus::PastDue, SubscriptionStatus::Paused])
            ->each(function (Subscription $subscription) use (&$written): void {
                $paused = $subscription->status === SubscriptionStatus::Paused;
                $at = $paused ? $subscription->paused_at : $subscription->current_period_ends_at;

                if ($at !== null) {
                    $written += $this->write($subscription->business_id, $paused ? ComplianceEventKind::PaymentPaused : ComplianceEventKind::PaymentLate, $at);
                }
            });

        return $written;
    }

    private function write(int $businessId, ComplianceEventKind $kind, ?CarbonInterface $at, ?string $detail = null, ?int $actorId = null): int
    {
        if ($at === null) {
            return 0;
        }

        $event = ComplianceEvent::query()->firstOrCreate(
            ['business_id' => $businessId, 'kind' => $kind, 'occurred_at' => $at],
            ['detail' => $detail === null ? null : mb_substr(trim($detail), 0, 200), 'actor_id' => $actorId],
        );

        return $event->wasRecentlyCreated ? 1 : 0;
    }
}
