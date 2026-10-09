<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionMarkKind;
use App\Enums\AdoptionSituation;
use App\Mail\StalledAdoptionReport;
use App\Messaging\Channels\Email;
use App\Models\AdoptionMark;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * An account that stalls is the thing nobody notices: the screen says it, but
 * only to whoever opens it. Daily, and only for what BECAME stalled since the
 * last run: the same account on the same step is told once, not every morning.
 */
#[Signature('atendia:adoption-alert')]
#[Description('Mails the team the accounts that have just passed the plazo of their step')]
class ReportStalledAdoption extends Command
{
    public function handle(): int
    {
        $told = AdoptionMark::lastBy(AdoptionMarkKind::Alerted);

        // The same rule as the screen's Trabadas tab: one definition of "stalled".
        $stalled = User::adoptionRows()
            ->filter(fn (AdoptionRowDto $row): bool => $row->situation === AdoptionSituation::Stalled
                && ! isset($told[$row->email.'|'.$row->step->value]))
            ->values();

        if ($stalled->isEmpty()) {
            $this->info('No newly stalled accounts.');

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

        // Marked only after handing the mail over: a run that dies before it would otherwise stay silent forever.
        $stalled->each(fn (AdoptionRowDto $row): bool => AdoptionMark::record($row->email, $row->step, AdoptionMarkKind::Alerted));

        $this->info($stalled->count().' stalled account(s) reported.');

        return self::SUCCESS;
    }
}
