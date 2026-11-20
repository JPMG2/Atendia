<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\Releases;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\ReleaseAvailable;
use App\Models\Business;
use App\Services\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Tells every business's bell that a version with something new is out. The
 * version ships with the code, so there is no event to hook: a short tick
 * notices the new number and the cache keeps the walk to once per version.
 */
class AnnounceRelease extends Command
{
    public const string ANNOUNCED_KEY = 'releases.announced';

    protected $signature = 'atendia:announce-release';

    protected $description = 'Put the newest version with news in every business bell, once';

    public function handle(Tenant $tenant): int
    {
        $news = (new Releases)->news;

        if ($news === null || Cache::get(self::ANNOUNCED_KEY) === $news['version'] || $this->outlivedTheBell($news['date'])) {
            return self::SUCCESS;
        }

        $failed = 0;

        // A suspended business is told why it is stopped, not what is new.
        Business::query()->whereNull('suspended_at')->lazyById()->each(function (Business $business) use ($tenant, $news, &$failed): void {
            $sent = rescue(fn (): bool => $tenant->for((int) $business->id, fn (): bool => (new Panel($business, [], ReleaseAvailable::class, [$news['version']]))->send()), false);
            $failed += $sent === false ? 1 : 0;
        });

        // With a failure the next tick walks again: a row already there never revives nor buzzes.
        if ($failed === 0) {
            Cache::forever(self::ANNOUNCED_KEY, $news['version']);
        }

        $this->info("Announced {$news['version']}, {$failed} failed");

        return self::SUCCESS;
    }

    /**
     * The row that stops a second announcement is pruned with the bell's window.
     * Past it, a lost cache would find no row and buzz every phone again for
     * old news, so a version that old is never announced.
     */
    private function outlivedTheBell(CarbonInterface $released): bool
    {
        return $released->lt(now()->subDays((int) config('atendia.bell.keep_days') - 1));
    }
}
