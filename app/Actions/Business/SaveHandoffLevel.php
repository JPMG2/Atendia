<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\HandoffLevel;
use App\Models\Business;

/**
 * The owner's dial: how eagerly THIS business hands a thread to a human.
 * The next exchange reads it straight from the business — no cache to bust.
 */
class SaveHandoffLevel
{
    public function handle(Business $business, HandoffLevel $level): void
    {
        $business->update(['handoff_level' => $level]);
    }
}
