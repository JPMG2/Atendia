<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the admin should do about an account, which is what the three tabs of
 * the adoption screen are. The code branches on it, so it stays an enum.
 */
enum AdoptionSituation: string
{
    case Stalled = 'stalled';
    case Starting = 'starting';
    case Active = 'active';

    public function label(): string
    {
        return __('adoption.tabs.'.$this->value);
    }
}
