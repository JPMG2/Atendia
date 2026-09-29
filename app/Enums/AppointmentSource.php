<?php

declare(strict_types=1);

namespace App\Enums;

/** Who booked it: the assistant on WhatsApp, a person in the panel, or the customer through the public link. */
enum AppointmentSource: string
{
    case Assistant = 'assistant';
    case Owner = 'owner';
    case Link = 'link';
}
