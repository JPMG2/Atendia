<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EmbedCatalog;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\Service;
use App\Services\Tenant;
use Illuminate\Console\Command;

/**
 * Picks up the AI work an outage left behind: catalog items without their
 * vector and knowledge documents whose indexing failed. Before this they
 * waited for the owner to save something again, which might be never.
 */
class RetryAiBacklog extends Command
{
    protected $signature = 'atendia:retry-ai-backlog';

    protected $description = 'Re-queue catalog vectors and knowledge indexing that failed during an outage';

    /** A failing document is retried at most this often, not on every tick. */
    private const int COOLDOWN_MINUTES = 30;

    public function handle(): int
    {
        [$documents, $businessIds] = app(Tenant::class)->for(null, fn (): array => [
            KnowledgeDocument::query()
                ->where('status', 'failed')
                ->where('updated_at', '<=', now()->subMinutes(self::COOLDOWN_MINUTES))
                ->limit(50)
                ->pluck('id'),
            Service::query()->where('is_active', true)->whereNull('embedding')->distinct()->pluck('business_id')
                ->merge(Product::query()->where('is_active', true)->whereNull('embedding')->distinct()->pluck('business_id'))
                ->unique(),
        ]);

        foreach ($documents as $documentId) {
            IndexKnowledgeDocument::dispatch((int) $documentId);
        }

        foreach ($businessIds as $businessId) {
            EmbedCatalog::dispatch((int) $businessId);
        }

        $this->info("Re-queued {$documents->count()} documents and {$businessIds->count()} catalogs");

        return self::SUCCESS;
    }
}
