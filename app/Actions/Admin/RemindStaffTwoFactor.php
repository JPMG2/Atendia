<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Classes\Main\StaffTwoFactor;
use App\Mail\StaffTwoFactorDeadline;
use App\Messaging\Channels\Email;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Tells a person of the team that their plazo for the second step is running.
 *
 * One door for the daily run (`$by` null) and for the owner's button on the
 * users screen: both share the one-per-day rule, so pressing it after the
 * morning mail, or twice, never mails the same person again that day.
 */
class RemindStaffTwoFactor
{
    public const string SENT = 'sent';

    public const string ALREADY = 'already';

    /** Nothing to remind: has it, is past the plazo, is closed, or cannot read the mail yet. */
    public const string NOT_DUE = 'not_due';

    /**
     * @return self::SENT|self::ALREADY|self::NOT_DUE
     *
     * @throws AuthorizationException When the person acting does not hold the permission.
     */
    public function handle(User $target, ?User $by = null): string
    {
        if ($by !== null) {
            Gate::forUser($by)->authorize('manage-admin-users');
        }

        $plazo = new StaffTwoFactor($target);

        // Past the plazo the panel already says it on every screen: a reminder would be noise.
        if ($plazo->deadline === null || $plazo->overdue || $target->trashed() || $target->email_verified_at === null) {
            return self::NOT_DUE;
        }

        if (! Cache::add('staff-two-factor-reminder:'.$target->id.':'.now()->toDateString(), true, now()->addDay())) {
            return self::ALREADY;
        }

        new Email($target, [(string) $target->email], StaffTwoFactorDeadline::class, [$plazo->deadline])->send();

        return self::SENT;
    }
}
