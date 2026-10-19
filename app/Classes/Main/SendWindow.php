<?php

declare(strict_types=1);

namespace App\Classes\Main;

use Carbon\CarbonInterface;

/**
 * The hours an automated message may land, read on the RECIPIENT's local
 * clock: one rule for the admin's phone and for a business's customers.
 */
final class SendWindow
{
    /** Whether the local moment falls inside the allowed hours of the day. */
    public static function containsHour(CarbonInterface $local): bool
    {
        $time = $local->format('H:i');

        return $time >= (string) config('atendia.schedule.send_window.from')
            && $time < (string) config('atendia.schedule.send_window.until');
    }

    /** Whether customers get automated messages on that local weekday (Sunday rests). */
    public static function isCustomerDay(CarbonInterface $local): bool
    {
        return in_array($local->dayOfWeek, (array) config('atendia.schedule.customer_days'), true);
    }
}
