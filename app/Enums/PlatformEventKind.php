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

    // Money.
    case ReceiptReceived = 'receipt_received';
    case PaymentLate = 'payment_late';
    case PaymentPaused = 'payment_paused';

    // Compliance: the assistant was silenced, or a file needs her eyes.
    case BusinessSuspended = 'business_suspended';
    case AppealReceived = 'appeal_received';
    case ContentToReview = 'content_to_review';

    public function severity(): PlatformEventSeverity
    {
        return match ($this) {
            self::BusinessSuspended, self::PaymentPaused => PlatformEventSeverity::Critical,
            self::BusinessJoined, self::ReceiptReceived, self::PaymentLate, self::AppealReceived,
            self::ContentToReview, self::WhatsAppDisconnected => PlatformEventSeverity::Important,
        };
    }

    /** The group of the filter on the board. */
    public function area(): string
    {
        return match ($this) {
            self::BusinessJoined, self::WhatsAppDisconnected => 'customers',
            self::ReceiptReceived, self::PaymentLate, self::PaymentPaused => 'money',
            self::BusinessSuspended, self::AppealReceived, self::ContentToReview => 'compliance',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BusinessJoined => 'users',
            self::WhatsAppDisconnected => 'whatsapp',
            self::ReceiptReceived => 'receipt',
            self::PaymentLate, self::PaymentPaused => 'clock',
            self::BusinessSuspended => 'ban',
            self::AppealReceived => 'message-circle',
            self::ContentToReview => 'shield-alert',
        };
    }

    /**
     * Whether the code can see the fact end. A new business never "ends":
     * somebody has to say it was attended.
     */
    public function resolvesItself(): bool
    {
        return match ($this) {
            self::WhatsAppDisconnected, self::PaymentLate, self::PaymentPaused, self::BusinessSuspended => true,
            default => false,
        };
    }

    public function label(): string
    {
        return __('platform_events.kinds.'.$this->value.'.label');
    }
}
