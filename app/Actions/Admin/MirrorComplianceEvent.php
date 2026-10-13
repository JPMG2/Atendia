<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\ComplianceEventKind;
use App\Enums\PlatformEventKind;
use App\Models\ComplianceEvent;

/**
 * Puts a business's compliance line on the platform's feed too.
 *
 * Every penalty already passes through one door (RecordComplianceEvent), so
 * the admin hears about all of them by listening there instead of touching
 * each of the places that suspend, appeal or pause.
 */
class MirrorComplianceEvent
{
    public function __construct(private readonly RaisePlatformEvent $raise, private readonly ResolvePlatformEvent $resolve) {}

    public function handle(ComplianceEvent $event): void
    {
        $business = $event->business_id;
        $detail = $event->readableDetail();

        match ($event->kind) {
            ComplianceEventKind::Suspended => $this->raise->handle(PlatformEventKind::BusinessSuspended, $business, $detail),
            ComplianceEventKind::Appealed => $this->raise->handle(PlatformEventKind::AppealReceived, $business, $detail),
            ComplianceEventKind::ContentRejected => $this->raise->handle(PlatformEventKind::ContentToReview, $business, $detail),
            ComplianceEventKind::PaymentLate => $this->raise->handle(PlatformEventKind::PaymentLate, $business, $detail),
            ComplianceEventKind::PaymentPaused => $this->paused($business, $detail),
            ComplianceEventKind::SuspensionLifted => $this->lifted($business),
            ComplianceEventKind::PaymentRegularized => $this->regularized($business),
            // A severe catch is followed by its own suspension line; a refused
            // receipt is her own decision, not news.
            ComplianceEventKind::ContentSevere, ComplianceEventKind::PaymentRejected => null,
        };
    }

    /** Paused supersedes late: the board shows the worse of the two, once. */
    private function paused(int $business, ?string $detail): void
    {
        $this->resolve->ofKind(PlatformEventKind::PaymentLate, $business);
        $this->raise->handle(PlatformEventKind::PaymentPaused, $business, $detail);
    }

    private function lifted(int $business): void
    {
        $this->resolve->ofKind(PlatformEventKind::BusinessSuspended, $business);
        $this->resolve->ofKind(PlatformEventKind::AppealReceived, $business);
    }

    private function regularized(int $business): void
    {
        $this->resolve->ofKind(PlatformEventKind::PaymentLate, $business);
        $this->resolve->ofKind(PlatformEventKind::PaymentPaused, $business);
    }
}
