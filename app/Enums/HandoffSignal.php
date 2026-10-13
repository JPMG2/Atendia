<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What PHP can tell about a customer message without asking the model:
 * the two cases where a wrong or skipped answer costs the most.
 */
enum HandoffSignal: string
{
    case HumanRequested = 'human_requested';
    case Emergency = 'emergency';
}
