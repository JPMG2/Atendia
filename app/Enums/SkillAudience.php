<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a skill answers: the business's customers on WhatsApp, or the owner
 * in the panel. Never mixed — the customer assistant must not read the
 * business's own statistics aloud to a stranger.
 */
enum SkillAudience: string
{
    case Customer = 'customer';
    case Owner = 'owner';
}
