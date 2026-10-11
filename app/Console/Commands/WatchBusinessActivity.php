<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\RankBusinessActivity;
use App\Mail\BusinessActivityStale;
use App\Messaging\Channels\Email;
use App\Models\PlatformFigure;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Tells the admin once when the stored ranking stops being recomputed.
 *
 * It is a separate command from the one it watches on purpose: a job cannot
 * report its own death. A figure that never ran is not an outage (a fresh
 * install has none until its first hour), so only a stored one that aged out
 * counts.
 */
#[Signature('atendia:business-activity-watch')]
#[Description('Mails the admin once when the business activity ranking stopped being recomputed')]
class WatchBusinessActivity extends Command
{
    public function handle(): int
    {
        $figure = PlatformFigure::read(PlatformFigure::ACTIVITY);

        if ($figure === null || ! $figure->isOlderThan(RankBusinessActivity::STALE_AFTER_MINUTES)) {
            return self::SUCCESS;
        }

        // The stamp of the stale figure is the key: one mail per outage, and a
        // recovery that breaks again carries a new stamp and earns a new mail.
        if (! Cache::add('business-activity-stale:'.$figure->computed_at->timestamp, true, now()->addDays(2))) {
            return self::SUCCESS;
        }

        // The admin's own account carries the mail, as in the other alerts.
        $admin = User::where('email', (string) config('atendia.admin_email'))->first();

        if ($admin === null) {
            $this->warn('No admin account for ADMIN_EMAIL: nothing sent.');

            return self::SUCCESS;
        }

        new Email($admin, [(string) $admin->email], BusinessActivityStale::class, [
            $figure->computed_at->format('d/m/Y H:i'),
            (int) $figure->computed_at->diffInMinutes(now()),
        ])->send();

        $this->info('Ranking stale since '.$figure->computed_at->toDateTimeString().': admin warned.');

        return self::SUCCESS;
    }
}
