<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Account\EraseClosedAccount;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('atendia:erase-closed-accounts')]
#[Description('Erases for good the accounts their owner closed once the restore window is over')]
class EraseClosedAccounts extends Command
{
    public function handle(EraseClosedAccount $erase): int
    {
        $erased = 0;

        foreach (User::closedPastRestoreWindow() as $user) {
            // One account that cannot be erased must not hold back the others; it is retried tomorrow.
            try {
                $erased += $erase->handle($user) ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info("Erased: {$erased}");

        return self::SUCCESS;
    }
}
