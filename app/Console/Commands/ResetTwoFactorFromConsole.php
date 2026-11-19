<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\ResetTwoFactor;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The same reset the owner does from the users screen, for the day someone
 * cannot sign in at all because the phone holding the codes was lost: a person
 * of the team, or a business owner who wrote to support (identity checked by
 * hand first). Whoever holds the server holds the key, so it asks for no
 * password; the audit line says "system" and the person gets an e-mail.
 */
#[Signature('atendia:two-factor-reset {email : The account whose second step is taken away}')]
#[Description('Resets the second step of one account (team or business owner), from the server')]
class ResetTwoFactorFromConsole extends Command
{
    public function handle(ResetTwoFactor $reset): int
    {
        $person = User::query()->where('email', (string) $this->argument('email'))->first();

        if ($person === null) {
            $this->error('No account has that e-mail.');

            return self::FAILURE;
        }

        $reset->handle($person, null);

        $this->info("{$person->email} has to turn the second step on again.");

        return self::SUCCESS;
    }
}
