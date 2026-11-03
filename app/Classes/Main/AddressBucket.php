<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * The address a per-IP limit counts by. A single home or phone line is given
 * a whole IPv6 /64 and may pick any address inside it, so counting the exact
 * address let one person rotate past every limit. IPv4 is counted as is.
 */
final class AddressBucket
{
    public static function of(?string $ip): string
    {
        $ip = (string) $ip;

        // An IPv4 written the IPv6 way (::ffff:1.2.3.4) is that one IPv4, not a /64 shared by every visitor.
        if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return substr($ip, 7);
        }

        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($ip) : false;

        if ($packed === false) {
            return $ip;
        }

        return (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
