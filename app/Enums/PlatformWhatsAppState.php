<?php

declare(strict_types=1);

namespace App\Enums;

/** What the platform's own WhatsApp line is doing, as the screen and the board read it. */
enum PlatformWhatsAppState: string
{
    /** Linked and answering: the only state in which anything can go out. */
    case Connected = 'connected';

    /** A QR was asked for and nobody has scanned it yet. */
    case Pairing = 'pairing';

    /** The instance exists but its session is closed. */
    case Closed = 'closed';

    /** The bridge does not run the instance yet: it is created the first time it is linked. */
    case Missing = 'missing';

    /** The bridge did not answer; nothing is claimed about the line. */
    case Unreachable = 'unreachable';

    /** No instance name is configured at all. */
    case NotConfigured = 'not_configured';

    public function isConnected(): bool
    {
        return $this === self::Connected;
    }

    /** Whether the screen may offer a QR: not while linked, and not when there is nothing to link. */
    public function canLink(): bool
    {
        return in_array($this, [self::Pairing, self::Closed, self::Missing], true);
    }
}
