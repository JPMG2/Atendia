<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuestionResolution;
use App\Jobs\AnalyzeConversation;
use App\Jobs\CollectSuggestions;
use App\Models\Conversation;
use App\Models\ConversationQuestion;
use App\Services\Tenant;
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

        // The sweep behind the per-analysis dispatch: a dead matcher or a backfill leaves questions unlinked.
        $businessIds = app(Tenant::class)->for(null, fn (): array => ConversationQuestion::query()
            ->whereIn('resolved_by', [QuestionResolution::Team, QuestionResolution::Nobody])
            ->whereNull('knowledge_suggestion_id')
            ->distinct()
            ->pluck('business_id')
            ->all());

        foreach ($businessIds as $businessId) {
            CollectSuggestions::dispatch((int) $businessId);
        }

        $this->info("Queued {$threads->count()} conversations");

        return self::SUCCESS;
    }
}
