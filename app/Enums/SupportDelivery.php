<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a reply actually left. The screen used to say "sent" no matter what, so
 * a reply to a business with no WhatsApp number read as answered and never
 * reached anybody; this says which of the four it was.
 */
enum SupportDelivery: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case NoContact = 'no_contact';
    case Failed = 'failed';

    /** Whether the business has it in hand; the only case where the ball changes sides. */
    public function reached(): bool
    {
        return $this === self::WhatsApp || $this === self::Email;
    }

    public function label(): string
    {
        return __('support.admin.delivery.'.$this->value);
    }

    /** What the person who just answered reads, with what to do when it did not leave. */
    public function outcome(): string
    {
        return __('support.admin.delivery.'.$this->value.'_outcome');
    }
}
