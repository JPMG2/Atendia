<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The life of an event: somebody has to see it, and a condition that went
 * away closes itself. The state is what tells her "still mine" from "done".
 */
enum PlatformEventState: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    /** Still worth a place on the board. */
    public function isActive(): bool
    {
        return $this !== self::Resolved;
    }

    public function label(): string
    {
        return __('platform_events.state.'.$this->value);
    }
}
