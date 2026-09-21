<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of an owner's word. Dismissed means they waved the ask
 * away — the card never nags again. Only Approved rows WITH consent are
 * ever published; the admin's hand is the last gate.
 */
enum TestimonialStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Dismissed = 'dismissed';
}
