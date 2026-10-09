<?php

declare(strict_types=1);

namespace App\Enums;

/** What was done about an account stuck on a step: she wrote to it, or the team was told. */
enum AdoptionMarkKind: string
{
    case Written = 'written';
    case Alerted = 'alerted';
}
