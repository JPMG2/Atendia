<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\NotifyPlatformAdmin;
use App\Actions\Admin\SweepPlatform;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The watcher's tick: look at every sensor, then tell the admin what is
 * critical and still unseen. Two minutes: a board that is read live should
 * never be older than the time it takes to look away and back.
 */
#[Signature('atendia:platform-sweep')]
#[Description('Reads every platform sensor, updates the feed and alerts the admin about unseen criticals')]
class SweepPlatformCommand extends Command
{
    public function handle(SweepPlatform $sweep, NotifyPlatformAdmin $notify): int
    {
        $result = $sweep->handle();
        $sent = $notify->handle();

        $this->info("Looked at {$result['looked']} sensors, {$result['problems']} with problems, {$sent} alerts sent.");

        return self::SUCCESS;
    }
}
