<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageDirection: string
{
    case In = 'in';
    case Out = 'out';
}
