<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RevenueSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Keeps the month's revenue so that next month can be compared with it.
 *
 * Runs daily and not once a month on purpose: the figure of the month in
 * course is kept fresh, and a server that was down on the 1st does not lose
 * the photo forever — the row is corrected, never duplicated.
 */
#[Signature('atendia:revenue-snapshot')]
#[Description('Stores this month revenue, its subscriptions and what moved since the previous month')]
class CaptureRevenueSnapshot extends Command
{
    public function handle(): int
    {
        $snapshot = RevenueSnapshot::capture();

        $this->info(sprintf(
            '%s: MRR %s from %d subscription(s). Gained %s, lost %s.',
            $snapshot->month->format('Y-m'),
            $snapshot->mrr,
            $snapshot->paying,
            $snapshot->gained,
            $snapshot->lost,
        ));

        return self::SUCCESS;
    }
}
