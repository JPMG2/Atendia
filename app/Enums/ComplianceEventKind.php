<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a business's file (legajo) can say happened. Code writes each one when
 * it happens, so this is an Enum: a new kind is a new line that records it.
 * The same rows carry the fault (a refused file) and its answer (a lifted
 * suspension): a file is a history of what was done, not of reprimands.
 */
enum ComplianceEventKind: string
{
    // Content moderation: a file the business uploaded or wrote was caught.
    case ContentRejected = 'content_rejected';
    case ContentSevere = 'content_severe';

    // The assistant was silenced, the owner answered, the admin decided.
    case Suspended = 'suspended';
    case Appealed = 'appealed';
    case SuspensionLifted = 'suspension_lifted';

    // Money: the grace days ran, the assistant paused, a receipt was refused.
    case PaymentRejected = 'payment_rejected';
    case PaymentLate = 'payment_late';
    case PaymentPaused = 'payment_paused';
    case PaymentRegularized = 'payment_regularized';

    /** Whether this row is something the business did wrong; the rest are what was done about it. */
    public function isFault(): bool
    {
        return in_array($this, [
            self::ContentRejected,
            self::ContentSevere,
            self::PaymentRejected,
            self::PaymentLate,
            self::PaymentPaused,
        ], true);
    }

    /** The area of the product it belongs to, for the filter and the icon. */
    public function area(): string
    {
        return match ($this) {
            self::ContentRejected, self::ContentSevere => 'content',
            self::Suspended, self::Appealed, self::SuspensionLifted => 'account',
            default => 'payment',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ContentSevere, self::Suspended, self::PaymentPaused => 'danger',
            self::ContentRejected, self::PaymentRejected, self::PaymentLate, self::Appealed => 'warning',
            self::SuspensionLifted, self::PaymentRegularized => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ContentRejected, self::ContentSevere => 'shield-alert',
            self::Suspended => 'ban',
            self::Appealed => 'message-circle',
            self::SuspensionLifted => 'circle-check',
            self::PaymentRejected => 'file-x',
            self::PaymentLate, self::PaymentPaused => 'clock',
            self::PaymentRegularized => 'circle-check',
        };
    }

    public function label(): string
    {
        return __('compliance.kinds.'.$this->value);
    }
}
