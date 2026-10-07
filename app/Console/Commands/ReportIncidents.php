<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\Incidents;
use App\Mail\IncidentsDigest;
use App\Messaging\Channels\Email;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The desk, brought to her instead of waiting to be opened.
 *
 * It stays quiet on a clean day on purpose: a mail that arrives every evening
 * saying "nothing happened" is the fastest way to teach somebody to archive it
 * unread, and then the one that matters goes with it.
 */
#[Signature('atendia:incidents-digest')]
#[Description('Mails the admin what went wrong today, and nothing at all on a clean day')]
class ReportIncidents extends Command
{
    public function handle(): int
    {
        $desk = Incidents::now();

        if ($desk->isEmpty) {
            $this->info('Nothing went wrong: no mail sent.');

            return self::SUCCESS;
        }

        // The admin's own account carries the mail: it is the one row that is
        // always there, and the Company screen may never have been saved.
        $admin = User::where('email', (string) config('atendia.admin_email'))->first();

        if ($admin === null) {
            $this->warn('No admin account for ADMIN_EMAIL: nothing sent.');

            return self::SUCCESS;
        }

        new Email($admin, [(string) $admin->email], IncidentsDigest::class, [$desk->all->all()])->send();

        $this->info($desk->all->count().' incident(s) reported.');

        return self::SUCCESS;
    }
}
