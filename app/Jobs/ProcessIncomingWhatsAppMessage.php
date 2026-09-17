<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\AsistenteAtendia;
use App\Models\Business;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One inbound WhatsApp text, already parsed and acknowledged. Queued so the
 * webhook answers Evolution instantly: the assistant plus its knowledge
 * search take seconds the webhook must not hold.
 */
class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $instance,
        public string $from,
        public string $senderName,
        public string $text,
        public string $messageId,
    ) {}

    public function handle(): void
    {
        $business = Business::forWhatsAppInstance($this->instance);

        if ($business === null) {
            // A live instance nobody claimed: worth an alarm, not a retry.
            Log::warning('whatsapp.incoming.unclaimed', [
                'instance' => $this->instance,
                'from' => $this->from,
            ]);

            return;
        }

        $evolution = app(EvolutionApi::class);

        // Best effort: a dead presence must never cost the actual reply.
        rescue(fn () => $evolution->markComposing($this->instance, $this->from), report: false);

        // The worker has no session: the job adopts the business so the
        // assistant's knowledge search runs inside the right tenant. A failed
        // send throws out of here and the queue retries the whole exchange.
        app(Tenant::class)->for((int) $business->id, function () use ($business, $evolution): void {
            $reply = new AsistenteAtendia($business)->answer($this->text)->text;

            $evolution->sendText($this->instance, $this->from, $reply);
        });
    }
}
