<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Dto\AdoptionRowDto;
use App\Mail\StalledAdoptionReport;
use App\Messaging\Channels\Email;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * An account that goes quiet halfway is the thing nobody notices: the screen
 * says it, but only to whoever opens it. Weekly, so it stays a nudge.
 */
#[Signature('atendia:adoption-alert {--days=7 : Days away before an account is reported}')]
#[Description('Mails the team the accounts that went quiet before their assistant answered')]
class ReportStalledAdoption extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        // Never signing in again is the worst case, not the absent one.
        $stalled = User::adoptionRows()
            ->filter(fn (AdoptionRowDto $row): bool => $row->isStalled && ($row->daysIdle ?? PHP_INT_MAX) >= $days)
            ->values();

        if ($stalled->isEmpty()) {
            $this->info('No stalled accounts.');

            return self::SUCCESS;
        }

        // The admin's own account carries the mail: it is the one row that is
        // always there, and the Company screen may never have been saved.
        $admin = User::where('email', (string) config('atendia.admin_email'))->first();

        if ($admin === null) {
            $this->warn('No admin account for ADMIN_EMAIL: nothing sent.');

            return self::SUCCESS;
        }

        new Email($admin, [(string) $admin->email], StalledAdoptionReport::class, [$stalled->all()])->send();

        $this->info($stalled->count().' stalled account(s) reported.');

        return self::SUCCESS;
    }
}
