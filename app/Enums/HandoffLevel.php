<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The owner's dial: how eagerly THIS business hands a thread to a human.
 * A doctor cannot be interrupted for every question; a realtor wants every
 * lead. The owner's insight, 2026-09-20.
 */
enum HandoffLevel: string
{
    case Eager = 'eager';
    case Balanced = 'balanced';
    case Minimal = 'minimal';
}
