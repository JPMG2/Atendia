<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\Service;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fills the missing name vectors of one business's catalog in batches: a
 * 2,000-row price list costs a handful of calls, not 2,000. Unique per
 * business, since every catalog save asks for it.
 */
class EmbedCatalog implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const int BATCH = 100;

    public function __construct(public int $businessId) {}

    public function uniqueId(): string
    {
        return (string) $this->businessId;
    }

    public function handle(KnowledgeEmbedder $embedder): void
    {
        app(Tenant::class)->for($this->businessId, function () use ($embedder): void {
            foreach ([Service::class, Product::class] as $model) {
                $model::query()->whereNull('embedding')->select(['id', 'name'])->chunkById(self::BATCH, function ($items) use ($embedder, $model): bool {
                    // A dead model must not fail the catalog save that queued this;
                    // the blanks stay null and the next save retries them.
                    $vectors = rescue(fn (): array => $embedder->embed($items->pluck('name')->map(fn ($name): string => (string) $name)->all()));

                    if ($vectors === null) {
                        return false;
                    }

                    // A raw update: a vector is no edit, so no audit, log or re-sync.
                    $items->values()->each(fn (Model $item, int $index) => $model::query()
                        ->whereKey($item->getKey())
                        ->toBase()
                        ->update(['embedding' => json_encode($vectors[$index])]));

                    return true;
                });
            }
        });
    }
}
