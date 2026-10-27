<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the platform can tell its admin. Code writes each one when the fact
 * happens, so this is an Enum: a new kind is a new line that records it.
 *
 * Severity belongs to the KIND and not to the row: whether a suspension needs
 * her now is a product decision, and a screen that let each writer pick would
 * drift into everything being critical.
 */
enum PlatformEventKind: string
{
    // Customers: somebody arrived, or somebody's line went quiet.
    case BusinessJoined = 'business_joined';
    case WhatsAppDisconnected = 'whatsapp_disconnected';
    case BusinessCancelled = 'business_cancelled';
    case MentionAccepted = 'mention_accepted';

    // Money.
    case ReceiptReceived = 'receipt_received';
    case PaymentApproved = 'payment_approved';
    case PlanChanged = 'plan_changed';
    case PaymentLate = 'payment_late';
    case PaymentPaused = 'payment_paused';

    // Compliance: the assistant was silenced, or a file needs her eyes.
    case BusinessSuspended = 'business_suspended';
    case AppealReceived = 'appeal_received';
    case ContentToReview = 'content_to_review';

    // The platform itself: a sensor turned red, or turned yellow. Two kinds
    // and not one with a loose severity, so a writer still cannot pick it.
    case SystemDown = 'system_down';
    case SystemDegraded = 'system_degraded';

    // An install of the phone app still carries a name the web no longer uses.
    case AppBrandStale = 'app_brand_stale';

    public function severity(): PlatformEventSeverity
    {
        return match ($this) {
            self::BusinessSuspended, self::PaymentPaused, self::SystemDown => PlatformEventSeverity::Critical,
            self::BusinessJoined, self::ReceiptReceived, self::PaymentLate, self::AppealReceived,
            self::ContentToReview, self::WhatsAppDisconnected, self::SystemDegraded, self::BusinessCancelled, self::MentionAccepted, self::AppBrandStale => PlatformEventSeverity::Important,
            self::PaymentApproved, self::PlanChanged => PlatformEventSeverity::Info,
        };
    }

    /** The group of the filter on the board. */
    public function area(): string
    {
        return match ($this) {
            self::BusinessJoined, self::WhatsAppDisconnected, self::BusinessCancelled, self::MentionAccepted => 'customers',
            self::ReceiptReceived, self::PaymentApproved, self::PlanChanged, self::PaymentLate, self::PaymentPaused => 'money',
            self::BusinessSuspended, self::AppealReceived, self::ContentToReview => 'compliance',
            self::SystemDown, self::SystemDegraded, self::AppBrandStale => 'system',
        };
    }

    /**
     * The kinds of one area, for the board's filter.
     *
     * @return list<self>
     */
    public static function inArea(string $area): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind): bool => $kind->area() === $area));
    }

    /**
     * Where the person acts on it: the named route and its query, so a line on
     * the board is a door and not just a sentence. Null when there is none.
     *
     * @return array{route: string, query: array<string, int>}|null
     */
    public function target(?int $businessId): ?array
    {
        return match ($this) {
            self::MentionAccepted => ['route' => 'admin.mentions', 'query' => []],
            self::AppBrandStale => ['route' => 'admin.company', 'query' => []],
            self::ReceiptReceived, self::PaymentApproved => ['route' => 'admin.payments', 'query' => []],
            self::PaymentLate, self::PaymentPaused => ['route' => 'admin.collections', 'query' => []],
            self::BusinessSuspended, self::AppealReceived, self::ContentToReview => $businessId === null
                ? ['route' => 'admin.compliance', 'query' => []]
                : ['route' => 'admin.compliance', 'query' => ['negocio' => $businessId]],
            self::BusinessJoined, self::WhatsAppDisconnected, self::BusinessCancelled, self::PlanChanged => $businessId === null
                ? null
                : ['route' => 'admin.businesses', 'query' => ['negocio' => $businessId]],
            self::SystemDown, self::SystemDegraded => null,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BusinessJoined => 'users',
            self::MentionAccepted => 'star',
            self::WhatsAppDisconnected => 'whatsapp',
            self::ReceiptReceived => 'receipt',
            self::PaymentApproved => 'check-circle',
            self::PlanChanged => 'repeat',
            self::BusinessCancelled => 'log-out',
            self::PaymentLate, self::PaymentPaused => 'clock',
            self::BusinessSuspended => 'ban',
            self::AppealReceived => 'message-circle',
            self::ContentToReview => 'shield-alert',
            self::SystemDown, self::SystemDegraded => 'triangle-alert',
            self::AppBrandStale => 'smartphone',
        };
    }

    /**
     * Whether the code can see the fact end. A new business never "ends":
     * somebody has to say it was attended.
     */
    public function resolvesItself(): bool
    {
        return match ($this) {
            self::WhatsAppDisconnected, self::PaymentLate, self::PaymentPaused, self::BusinessSuspended,
            self::SystemDown, self::SystemDegraded => true,
            default => false,
        };
    }

    public function label(): string
    {
        return __('platform_events.kinds.'.$this->value.'.label');
    }
}
