<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * A job that does nothing but leave the hour it ran. The sweep reads it: if
 * the queue worker is dead no failure is ever logged, so the only proof it
 * works is a job that arrives.
 */
class PlatformHeartbeat implements ShouldQueue
{
    use Queueable;

    public const string CACHE_KEY = 'platform.heartbeat.queue';

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, now()->getTimestamp(), now()->addDay());
    }
}
