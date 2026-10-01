<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle Zendesk settled on, minus the ones we cannot honour yet. New
 * is where every ticket lands and never returns to; Waiting is ours asking
 * the person something back.
 */
enum SupportTicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __('support.statuses.'.$this->value);
    }

    /** The tone the badge takes; the same four the panel already speaks. */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Open => 'info',
            self::Waiting => 'brand',
            self::Resolved, self::Closed => 'success',
        };
    }

    /** Still ours to answer: what the admin inbox counts as pending. */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Open, self::Waiting], true);
    }
}
