<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\DigestWriter;
use App\Models\Business;
use App\Services\EvolutionApi;
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
        $businesses = Business::query()
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->whereNotNull('fallback_whatsapp_number')
            ->get();

        foreach ($businesses as $business) {
            $entries = (array) Cache::pull('wa:digest:'.$business->id.':'.now()->format('Y-m-d'));

            if ($entries === []) {
                continue;
            }

            $evolution->sendText(
                (string) $business->whatsapp_instance,
                (string) preg_replace('/\D/', '', (string) $business->fallback_whatsapp_number),
                $this->compose($business, $entries),
            );

            $this->info("Digest sent for {$business->name}");
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
