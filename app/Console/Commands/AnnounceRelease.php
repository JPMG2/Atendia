<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\Releases;
use App\Mail\ReleaseNews;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\ReleaseAvailable;
use App\Models\Business;
use App\Models\PanelNotification;
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
            $sent = rescue(fn (): bool => $tenant->speakingAs($business, fn (): bool => $this->announceTo($business, $news)), false);
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
     * The bell row, and the mail only with it: a business that already has the
     * row was told, so a re-walk (lost cache, a failure elsewhere) mails nobody twice.
     *
     * @param  array{version: string, title: string, items: list<string>}  $news
     */
    private function announceTo(Business $business, array $news): bool
    {
        if (PanelNotification::query()->where('dedupe_key', 'release:'.$news['version'])->exists()) {
            return true;
        }

        if (! (new Panel($business, [], ReleaseAvailable::class, [$news['version']]))->send()) {
            return false;
        }

        if ((int) config('atendia.releases.mail_owners') === 1 && filled($business->billing_email)) {
            $release = ['version' => $news['version'], 'title' => $news['title'], 'items' => $news['items']];
            (new Email($business, [(string) $business->billing_email], ReleaseNews::class, [$release]))->send();
        }

        return true;
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
