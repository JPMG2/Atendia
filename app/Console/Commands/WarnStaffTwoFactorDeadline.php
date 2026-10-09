<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\RemindStaffTwoFactor;
use App\Classes\Main\StaffTwoFactor;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Past the plazo the panel only opens the security page, so the day before is
 * the last chance to say it with time to act. Daily; the action keeps it to one
 * mail per person per day, so a second run never repeats it.
 */
#[Signature('atendia:staff-two-factor-warning')]
#[Description('Mails each team member whose plazo for the second step ends tomorrow')]
class WarnStaffTwoFactorDeadline extends Command
{
    public function handle(RemindStaffTwoFactor $remind): int
    {
        $told = User::query()
            ->permission('access-admin-panel')
            ->with('business')
            ->get()
            ->filter(fn (User $person): bool => (new StaffTwoFactor($person))->daysLeft === 1)
            ->filter(fn (User $person): bool => $remind->handle($person) === RemindStaffTwoFactor::SENT)
            ->count();

        $this->info($told === 0 ? 'Nobody reaches the end of the plazo tomorrow.' : "{$told} person(s) warned.");

        return self::SUCCESS;
    }
}
