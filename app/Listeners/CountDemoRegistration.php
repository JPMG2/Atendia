<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\DemoMetric;
use Illuminate\Auth\Events\Registered;

/**
 * The funnel's closing step: a registration coming from a visitor who
 * tried the hero demo first. The session flag is the attribution — no
 * session (console, tests without one) simply counts nothing.
 */
class CountDemoRegistration
{
    public function handle(Registered $event): void
    {
        if ((int) session()->get('demo_messages', 0) > 0) {
            DemoMetric::bump('registrations');
        }
    }
}
