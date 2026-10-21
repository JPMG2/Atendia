<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an account may use the mobile app, and if not, why. The app is for
 * paying customers: the trial and the unpaid are sent back to the web, and
 * the reason travels so the app can say what to do instead of a bare "no".
 */
enum AppAccess: string
{
    case Allowed = 'allowed';
    case NoBusiness = 'no_business';
    case NoPlan = 'no_plan';
    case OnTrial = 'on_trial';
    case TrialEnded = 'trial_ended';
    case Paused = 'paused';
    case Ended = 'ended';
    case Suspended = 'suspended';

    public function isAllowed(): bool
    {
        return $this === self::Allowed;
    }
}
