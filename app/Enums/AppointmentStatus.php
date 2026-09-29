<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a booking stands. Born Confirmed: the assistant closes the slot on
 * the spot ("confirma sin que muevas un dedo"), so a pending state would be
 * a promise nobody keeps. Done and NoShow are what the owner marks after.
 */
enum AppointmentStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Done = 'done';
    case NoShow = 'no_show';

    /** The ones that still hold their slot: only these block a free hour. */
    public function holdsSlot(): bool
    {
        return $this !== self::Cancelled;
    }
}
