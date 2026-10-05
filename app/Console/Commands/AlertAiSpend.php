<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\AiSpend;
use App\Mail\AiSpendAlert;
use App\Messaging\Channels\Email;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A client whose AI eats its own plan is the one thing the screen cannot tell
 * her unless she opens it, and by the time she does the month is spent. Weekly,
 * so it stays a nudge and not a feed nobody reads.
 */
#[Signature('atendia:ai-alert')]
#[Description('Mails the admin the businesses whose AI went over their plan threshold this month')]
class AlertAiSpend extends Command
{
    public function handle(): int
    {
        $spend = AiSpend::of(now());

        $over = $spend->board
            ->filter(fn (object $row): bool => $row->isOverAlert)
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'cost' => '$'.number_format((float) $row->totals->cost, 2, ',', '.'),
                'share' => (int) round($row->share * 100),
                'limit' => (int) round($row->alertShare * 100),
            ])
            ->values();

        if ($over->isEmpty()) {
            $this->info('No business is over its plan threshold.');

            return self::SUCCESS;
        }

        // The admin's own account carries the mail: it is the one row that is
        // always there, and the Company screen may never have been saved.
        $admin = User::where('email', (string) config('atendia.admin_email'))->first();

        if ($admin === null) {
            $this->warn('No admin account for ADMIN_EMAIL: nothing sent.');

            return self::SUCCESS;
        }

        new Email($admin, [(string) $admin->email], AiSpendAlert::class, [
            $over->all(),
            now()->translatedFormat('F Y'),
        ])->send();

        $this->info($over->count().' business(es) over threshold reported.');

        return self::SUCCESS;
    }
}
