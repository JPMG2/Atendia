<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Tenant;
use App\Services\Topics\SuggestionCollector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Folds a business's unanswered questions into its suggestions. Unique per
 * business: two runs at once would each create the same suggestion.
 */
class CollectSuggestions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** A backfill stops here and the next sweep carries on. */
    private const int MAX_BATCHES = 20;

    public function __construct(public int $businessId) {}

    public function uniqueId(): string
    {
        return (string) $this->businessId;
    }

    public function handle(SuggestionCollector $collector): void
    {
        app(Tenant::class)->for($this->businessId, function () use ($collector): void {
            foreach (range(1, self::MAX_BATCHES) as $ignored) {
                if ($collector->collect() === 0) {
                    return;
                }
            }
        });
    }
}
