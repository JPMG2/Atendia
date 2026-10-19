<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\KnowledgeBase;
use App\Mail\BusinessKnowledgeDigest;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The owner's weekly learning recap: what the assistant was taught and what
 * it still could not answer, mailed to the account address — the teaching
 * loop closed without a WhatsApp nobody asked for.
 */
class SendKnowledgeDigests extends Command
{
    protected $signature = 'atendia:knowledge-digest';

    protected $description = 'Mail each connected business its weekly learning digest';

    public function handle(): int
    {
        $weekday = (int) config('atendia.schedule.knowledge_digest.weekday');
        $time = (string) config('atendia.schedule.knowledge_digest.time');

        $businesses = Business::query()
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->whereNotNull('billing_email')
            ->with('subscription')
            ->get()
            // Same dial as the evening digest: the weekly recap is sold from
            // the middle plan up, so the entry plan does not get it.
            ->filter(fn (Business $business): bool => $business->plan()->hasDailyDigest)
            ->filter(fn (Business $business): bool => $business->isDueAt($time, $weekday));

        foreach ($businesses as $business) {
            // Once per business per local week, and one failure never skips the rest.
            if (! Cache::add('knowledge-digest:'.$business->id.':'.now($business->localTimezone())->format('o-W'), true, now()->addDays(8))) {
                continue;
            }

            rescue(fn () => app(Tenant::class)->speakingAs($business, function () use ($business): void {
                $knowledge = new KnowledgeBase($business);
                $taught = $knowledge->weeklyTaughtCount;
                $missed = $knowledge->weeklySuggestionRecap;

                // Silent when the week taught and missed nothing: an empty
                // recap reads as noise, not as progress.
                if ($taught === 0 && $missed['count'] === 0) {
                    return;
                }

                (new Email($business, [(string) $business->billing_email], BusinessKnowledgeDigest::class, [$taught, $missed]))->send();

                $this->info("Knowledge digest mailed for {$business->name}");
            }));
        }

        return self::SUCCESS;
    }
}
