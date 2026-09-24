<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\KnowledgeBase;
use App\Models\Business;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The owner's weekly learning recap: what the assistant was taught and what
 * it still could not answer, delivered to the human fallback number over
 * the business's own instance — the teaching loop closed from the pocket.
 */
class SendKnowledgeDigests extends Command
{
    protected $signature = 'atendia:knowledge-digest';

    protected $description = 'Send each connected business its weekly learning digest';

    public function handle(EvolutionApi $evolution): int
    {
        $weekday = (int) config('atendia.schedule.knowledge_digest.weekday');
        $time = (string) config('atendia.schedule.knowledge_digest.time');

        $businesses = Business::query()
            ->whereNotNull('whatsapp_instance')
            ->whereNotNull('whatsapp_connected_at')
            ->whereNotNull('fallback_whatsapp_number')
            ->get()
            ->filter(fn (Business $business): bool => $business->isDueAt($time, $weekday));

        foreach ($businesses as $business) {
            // Once per business per local week, and one failure never skips the rest.
            if (! Cache::add('knowledge-digest:'.$business->id.':'.now($business->localTimezone())->format('o-W'), true, now()->addDays(8))) {
                continue;
            }

            rescue(fn () => app(Tenant::class)->for((int) $business->id, function () use ($evolution, $business): void {
                $text = $this->compose($business);

                if ($text === null) {
                    return;
                }

                $evolution->sendText((string) $business->whatsapp_instance, $business->ownerWhatsAppDigits(), $text);

                $this->info("Knowledge digest sent for {$business->name}");
            }));
        }

        return self::SUCCESS;
    }

    /**
     * Silent when the week taught and missed nothing: an empty recap reads
     * as noise, not as progress — same rule as the referral brag.
     */
    private function compose(Business $business): ?string
    {
        $knowledge = new KnowledgeBase($business);
        $taught = $knowledge->weeklyTaughtCount;
        $missed = $knowledge->weeklySuggestionRecap;

        if ($taught === 0 && $missed['count'] === 0) {
            return null;
        }

        $lines = [__('assistant.digest.knowledge_header', ['business' => $business->name])];

        if ($taught > 0) {
            $lines[] = trans_choice('assistant.digest.knowledge_taught', $taught, ['count' => $taught]);
        }

        if ($missed['count'] > 0) {
            $lines[] = trans_choice('assistant.digest.knowledge_missed', $missed['count'], [
                'count' => $missed['count'],
                'question' => (string) $missed['top'],
            ]);
            $lines[] = __('assistant.digest.knowledge_footer', ['url' => route('assistant')]);
        }

        return implode("\n", $lines);
    }
}
