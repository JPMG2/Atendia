<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    // A receipt the admin still has to verify.
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
