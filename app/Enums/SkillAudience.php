<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a skill answers: the business's customers on WhatsApp, the owner in
 * the panel, or the platform admin. Never mixed — the customer assistant must
 * not read the business's own statistics aloud to a stranger, and the owner's
 * must never see another business.
 */
enum SkillAudience: string
{
    case Customer = 'customer';
    case Owner = 'owner';
    case Admin = 'admin';
}
