<?php

declare(strict_types=1);

namespace App\Enums;

enum GuardVerdict: string
{
    case Ok = 'ok';
    case Muted = 'muted';
    case TooMany = 'too_many';
    case Offensive = 'offensive';
}
