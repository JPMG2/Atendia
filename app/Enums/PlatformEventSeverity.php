<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How loudly a platform event speaks. Only the first one is allowed to
 * interrupt a person: paging on everything teaches her to ignore the phone.
 */
enum PlatformEventSeverity: string
{
    case Critical = 'critical';
    case Important = 'important';
    case Info = 'info';

    /** Worst first, so the board can sort by it. */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 3,
            self::Important => 2,
            self::Info => 1,
        };
    }

    /** The `status-tag` modifier the board paints it with. */
    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Important => 'warning',
            self::Info => 'neutral',
        };
    }

    /** Whether it may reach her phone on its own. */
    public function interrupts(): bool
    {
        return $this === self::Critical;
    }

    public function label(): string
    {
        return __('platform_events.severity.'.$this->value);
    }
}
