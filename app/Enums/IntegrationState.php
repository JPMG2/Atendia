<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Connected: on and answering. Failing: configured but not answering — the
 * one that needs a hand. Off: not configured, which is a choice, not a fault.
 */
enum IntegrationState: string
{
    case Connected = 'connected';
    case Failing = 'failing';
    case Off = 'off';
}
