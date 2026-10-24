<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Business\ComposeDailyDigest;
use App\Mail\BusinessDailyDigest;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\Push;
use App\Messaging\Push\DailyDigestReady;
use App\Models\Business;
use App\Models\DailyDigest;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The owner's closing report: what the assistant handled today. It goes by
 * e-mail and a generic push to the app, never by an unsolicited WhatsApp: a
 * number that writes first to its owner every night looks like spam to
 * WhatsApp. The owner can still ask for it there by writing "resumen".
 * Reads the tally the reply job keeps.
 */
class SendDailyDigests extends Command
{
    protected $signature = 'atendia:daily-digest';

    protected $description = 'Mail each connected business the summary of its day';

    public function handle(ComposeDailyDigest $compose): int
    {
        $time = (string) config('atendia.schedule.whatsapp_digest');

        $businesses = Business::query()
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->whereNotNull('billing_email')
            ->with('subscription')
            ->get()
            // The card sells the digest from the middle plan up: without this
            // the entry plan got it too and the promise meant nothing.
            ->filter(fn (Business $business): bool => $business->plan()->hasDailyDigest)
            ->filter(fn (Business $business): bool => $business->isDueAt($time));

        foreach ($businesses as $business) {
            // A rolling tally, not a UTC-dated one: everything since the last
            // digest, the local evening included. Cleared only once it LEFT.
            $key = 'wa:digest:'.$business->id;
            $entries = (array) Cache::get($key, []);

            if ($entries === []) {
                continue;
            }

            rescue(fn () => app(Tenant::class)->speakingAs($business, function () use ($compose, $business, $entries, $key): void {
                $digest = $compose->handle($business, array_values($entries));

                if (! (new Email($business, [(string) $business->billing_email], BusinessDailyDigest::class, [$digest]))->send()) {
                    return;
                }

                // Only what actually left is kept: a failed send leaves the tally for the next run.
                DailyDigest::keep($business, $digest);
                Cache::forget($key);

                // The report now exists for the app as well: tell the owner's phone it is ready.
                (new Push($business, [], DailyDigestReady::class))->send();

                $this->info("Digest mailed for {$business->name}");
            }));
        }

        return self::SUCCESS;
    }
}
