<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\ResetTwoFactor;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The same reset the owner does from the users screen, for the day she cannot
 * sign in at all (her own phone is the one that was lost). Whoever holds the
 * server holds the key, so it asks for no password; the audit line says "system".
 */
#[Signature('atendia:two-factor-reset {email : The account whose second step is taken away}')]
#[Description('Resets the second step of one person of the team, from the server')]
class ResetTwoFactorFromConsole extends Command
{
    public function handle(ResetTwoFactor $reset): int
    {
        $person = User::query()->permission('access-admin-panel')->where('email', (string) $this->argument('email'))->first();

        if ($person === null) {
            $this->error('No person of the team has that e-mail.');

            return self::FAILURE;
        }

        $reset->handle($person, null);

        $this->info("{$person->email} has to turn the second step on again.");

        return self::SUCCESS;
    }
}
