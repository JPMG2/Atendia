<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What we can honestly say about a business's WhatsApp link.
 *
 * Unverified is the point of this enum: the bridge did not answer, so the
 * stamped column is the last thing we KNEW, not what is true now. Claiming
 * "connected" from a stale stamp is what let a lost pairing read as green.
 */
enum WhatsAppLinkState: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Unverified = 'unverified';
}
