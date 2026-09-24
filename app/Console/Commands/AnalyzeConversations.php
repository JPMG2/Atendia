<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AnalyzeConversation;
use App\Models\Conversation;
use Illuminate\Console\Command;

/**
 * Queues every finished thread with an unanalyzed stretch. The scheduler
 * runs it bare; by hand, --days bounds a backfill of the history.
 */
class AnalyzeConversations extends Command
{
    protected $signature = 'atendia:analyze-conversations {--days= : Only threads active within the last N days}';

    protected $description = 'Queue the AI analysis of finished conversations';

    public function handle(): int
    {
        $days = $this->option('days');

        $threads = Conversation::query()
            ->readyForAnalysis((int) config('atendia.analysis.idle_hours'), $days !== null ? (int) $days : null)
            ->get(['id', 'business_id']);

        foreach ($threads as $thread) {
            AnalyzeConversation::dispatch((int) $thread->business_id, (int) $thread->id);
        }

        $this->info("Queued {$threads->count()} conversations");

        return self::SUCCESS;
    }
}
