<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a sensor says at the last look. `Unknown` is its own state on purpose:
 * a thing that could not be read is not green, and it is not red either.
 */
enum PlatformSensorStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Crit = 'crit';
    case Unknown = 'unknown';

    /** Worst first, so a group and the board can show their worst thing. */
    public function weight(): int
    {
        return match ($this) {
            self::Crit => 3,
            self::Warn => 2,
            self::Unknown => 1,
            self::Ok => 0,
        };
    }

    /** The CSS state word the tile paints itself with. */
    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'ok',
            self::Warn => 'warn',
            self::Crit => 'crit',
            self::Unknown => 'info',
        };
    }

    /** Whether it counts as a problem on the board. */
    public function isProblem(): bool
    {
        return $this === self::Warn || $this === self::Crit;
    }
}
