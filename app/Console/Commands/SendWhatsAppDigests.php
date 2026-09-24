<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\DigestWriter;
use App\Models\Business;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The owner's nightly recap: what the assistant handled today, delivered
 * to the human fallback number over the business's own instance. Reads the
 * cache tally the reply job keeps — the conversations table upgrades this
 * to real history the day it exists.
 */
class SendWhatsAppDigests extends Command
{
    protected $signature = 'atendia:whatsapp-digest';

    protected $description = 'Send each connected business its daily WhatsApp activity digest';

    public function handle(EvolutionApi $evolution): int
    {
        $time = (string) config('atendia.schedule.whatsapp_digest');

        $businesses = Business::query()
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->whereNotNull('fallback_whatsapp_number')
            ->get()
            ->filter(fn (Business $business): bool => $business->isDueAt($time));

        foreach ($businesses as $business) {
            // A rolling tally, not a UTC-dated one: everything since the last
            // digest, the local evening included. Cleared only once it LEFT.
            $key = 'wa:digest:'.$business->id;
            $entries = (array) Cache::get($key, []);

            if ($entries === []) {
                continue;
            }

            rescue(fn () => app(Tenant::class)->for((int) $business->id, function () use ($evolution, $business, $entries, $key): void {
                $evolution->sendText((string) $business->whatsapp_instance, $business->ownerWhatsAppDigits(), $this->compose($business, $entries));

                Cache::forget($key);

                $this->info("Digest sent for {$business->name}");
            }));
        }

        return self::SUCCESS;
    }

    /** @param  non-empty-list<array{from: string, q: string, a: string}>  $entries */
    private function compose(Business $business, array $entries): string
    {
        $contacts = count(array_unique(array_column($entries, 'from')));

        $header = __('assistant.digest.header', [
            'business' => $business->name,
            'messages' => count($entries),
            'contacts' => $contacts,
        ]);

        // A dead model never silences the digest: the counts alone still land.
        $summary = rescue(function () use ($entries): string {
            $log = implode("\n", array_map(
                fn (array $entry): string => "Cliente {$entry['from']}: {$entry['q']} → {$entry['a']}",
                $entries,
            ));

            return trim((string) DigestWriter::make()->prompt($log)->text);
        }, '', report: true);

        $digest = $summary === '' ? $header : $header."\n\n".$summary;

        // The referral brag rides the mail everyone already reads: silent at
        // zero — an empty cheer reads as pity, not as progress.
        $referred = $business->referredThisWeek();

        if ($referred > 0) {
            $digest .= "\n\n".trans_choice('assistant.digest.referrals', $referred, ['count' => $referred]);
        }

        return $digest;
    }
}
