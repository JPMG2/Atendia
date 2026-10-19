<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a business stands with the offer to be mentioned on the platform's
 * networks. The follow is a condition: nothing is published for an account
 * that does not follow ours.
 */
enum MentionStatus: string
{
    case Dismissed = 'dismissed';
    case Pending = 'pending';
    case NotFollowing = 'not_following';
    case Ready = 'ready';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    /** Accepted and not over: the business is waiting on somebody, so no new offer. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::NotFollowing, self::Ready], true);
    }

    /** An answer of "no": the offer waits and, after enough of them, stops. */
    public function isRefusal(): bool
    {
        return in_array($this, [self::Dismissed, self::Withdrawn], true);
    }

    public function label(): string
    {
        return __('mentions.status.'.$this->value);
    }

    /** The tone of the status tag in the admin tables. */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::NotFollowing => 'danger',
            self::Ready, self::Published => 'success',
            self::Dismissed, self::Withdrawn => 'neutral',
        };
    }
}
