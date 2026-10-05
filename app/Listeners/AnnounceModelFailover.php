<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentFailedOver;

/**
 * Says out loud that an agent fell to its backup model.
 *
 * The ladder in `ai_tasks` makes a lab outage survivable, and that is exactly
 * why it must not be silent: answering from the backup for three days without
 * anybody knowing is the same as not having noticed the outage.
 */
class AnnounceModelFailover
{
    public function handle(AgentFailedOver $event): void
    {
        Log::warning('AI failover: an agent fell to its backup model.', [
            'agent' => class_basename($event->agent),
            'failed_provider' => (string) $event->provider,
            'failed_model' => $event->model,
            'reason' => $event->exception->getMessage(),
        ]);
    }
}
