<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Whether the platform's own staff owes the second step, and until when.
 *
 * The rule belongs to the people who open the admin panel (admin and support):
 * a client is offered it, never asked. The plazo is a platform setting; a
 * person the owner reset has none, because cutting them off was the point.
 */
final class StaffTwoFactor
{
    public function __construct(private readonly User $user) {}

    /** Days given to turn it on; zero means the platform does not ask. */
    private int $graceDays {
        get => (int) config('atendia.security.staff_two_factor.grace_days');
    }

    /** True while this person is expected to have it and does not. */
    public bool $applies {
        get => $this->graceDays > 0
            && $this->user->can('access-admin-panel')
            && ! $this->user->sendsLoginCodesByWhatsApp();
    }

    /** The last moment without it; null when nothing is asked of this person. */
    public ?CarbonImmutable $deadline {
        get {
            if (! $this->applies) {
                return null;
            }

            if ($this->user->secondStepResetAt() !== null) {
                return $this->user->secondStepResetAt();
            }

            $since = CarbonImmutable::parse((string) config('atendia.security.staff_two_factor.since'));
            $created = CarbonImmutable::instance($this->user->created_at);

            return ($created->greaterThan($since) ? $created : $since)->addDays($this->graceDays);
        }
    }

    /** Past the plazo: this person can only reach the security page until it is on. */
    public bool $overdue {
        get => $this->deadline?->isPast() ?? false;
    }

    /** Whole days left before the plazo ends; null when nothing is asked, 0 on the last day. */
    public ?int $daysLeft {
        get => $this->deadline === null
            ? null
            : max(0, (int) now()->startOfDay()->diffInDays($this->deadline->startOfDay(), false));
    }
}
